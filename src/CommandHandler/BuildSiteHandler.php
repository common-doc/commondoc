<?php

declare(strict_types=1);

/**
 * This file is part of the commondoc package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CommonDoc\CommandHandler;

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\Exception\ExceptionInterface;
use CommonDoc\Exception\LogicException;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Input\Markdown;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Record\BuildRecord;
use CommonDoc\Record\Progress;
use CommonDoc\Record\Reason;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use InvalidArgumentException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Exception\CommonMarkException;
use League\Config\Exception\ConfigurationExceptionInterface;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\RouteCollectorInterface;
use Psl\Exception\ExceptionInterface as PslExceptionInterface;
use Psl\File;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;
use Webware\FileWriter\FileWriter;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\Command\CommandResultInterface;
use Webware\MessageBus\CommandHandlerInterface;
use Webware\MessageBus\MessageStatus;

use function array_pop;
use function explode;
use function rtrim;
use function sprintf;
use function strlen;
use function substr;

/**
 * The coordinator over the passes a build is made of.
 *
 * The order is the design: every document is discovered before anything is
 * rendered, and every route is registered before the first render, because a
 * link may target a document the scan has not reached yet.
 *
 * One traversal. `Tree` walks the filesystem once and the two filters partition
 * its result, so adding a type costs a pass over an array rather than another
 * pass over the disk.
 *
 * One converter per build. Both per-build objects are asked for with `build()`
 * rather than `get()`, because neither can be shared: the converter carries a
 * link rewriter bound to this build's own document list, and a page belongs to
 * one document. The service manager never caches what it builds, so a second
 * build in one process cannot see the first build's listener, and each document
 * gets its own page.
 *
 * Two failure policies, and the difference is deliberate. A document that cannot
 * be rendered or written is recorded and the build continues, because the
 * operator wants the whole list rather than the first problem. An asset that
 * cannot be copied aborts, because a missing stylesheet is structural rather
 * than a content problem, and `BuildRecord` describes documents rather than
 * files.
 *
 * @api
 */
final readonly class BuildSiteHandler implements CommandHandlerInterface
{
    private const string SEPARATOR = '/';

    /**
     * @param ServiceLocatorInterface $services Where the per-build objects come from.
     *        A converter and a page are built per build and per document, so they
     *        cannot be injected as instances; this is the seam that builds them.
     */
    public function __construct(
        private DocumentRoot $root,
        private RouteCollectorInterface $collector,
        private PageContext $context,
        private NotServedMiddleware $middleware,
        private ServiceLocatorInterface $services,
    ) {}

    /**
     * Two path fragments joined with one separator, whatever the first had.
     *
     * @return non-empty-string
     */
    private static function join(string $directory, string $path): string
    {
        return sprintf('%s%s%s', rtrim($directory, characters: '/\\'), self::SEPARATOR, $path);
    }

    /**
     * A page label for a document, derived mechanically from its own path.
     *
     * The file name without its document suffix is used, with no special case
     * for `index`, because a better label needs a source this build does not have
     * yet: the document's first heading, or a front-matter field. Deriving
     * something deterministic is better than inventing navigation vocabulary
     * here, and the label is cosmetic until that source exists.
     */
    private static function labelFor(string $document): string
    {
        $segments = explode(self::SEPARATOR, $document);

        return substr(
            string: array_pop($segments),
            offset: 0,
            length: -strlen(Markdown::EXTENSION),
        );
    }

    /**
     * Build the site and report what was written and what failed.
     *
     * Both kinds of file fail the same way and are reported the same way. A
     * build that stopped at the first unreadable asset would name nothing, and an
     * operator cannot fix a path they were not told about.
     *
     * @throws InvalidArgumentException When a scanned path has no source, which is a
     *         defect in this package rather than in the documentation
     * @throws LogicException When the scan phase has not run
     * @throws DuplicateRouteException When two documents publish at one path
     * @throws ContainerExceptionInterface When a per-build object cannot be built
     * @throws Type\Exception\ExceptionInterface When a built object is the wrong type
     */
    public function handle(BuildSiteCommand $command): CommandResultInterface
    {
        $target = $command->getOutput();

        // Read rather than discovered: the scan ran as its own phase, so what a
        // build covers is already known before a document is read. Nothing here
        // knows how many repositories were walked.
        $scan      = $command->getScan();
        $documents = $scan->documents;
        $assets    = $scan->assets;

        $this->seed($documents);

        // Built rather than fetched: the converter carries a link rewriter bound
        // to these two lists, and a shared one would carry the previous build's.
        $converter = Type\instance_of(ConverterInterface::class)
            ->coerce(
                $this->services->build(ConverterInterface::class, [
                    'documents' => $documents,
                    'assets'    => $assets,
                    'origins'   => $scan->origins,
                ]),
            );

        $written = [];
        $failed  = [];

        foreach ($documents as $document) {
            try {
                $this->write($converter, $scan->sourceOf($document), $document, $target);
                $written[] = $document;
            } catch (
                CommonMarkException|ConfigurationExceptionInterface|ExceptionInterface|FileWriterException|PslExceptionInterface $failure
            ) {
                $failed[$document] = Reason::of($failure);
            }
        }

        // The assets were copied by their own phase, so their progress arrives on
        // the message rather than being produced here. Composing rather than
        // re-deciding is the whole point of the split: this method no longer knows
        // that an asset is a thing that can fail independently.
        $progress = $command->getProgress()->and(new Progress(
            output: $written,
            failed: $failed,
        ));

        $record = new BuildRecord(
            input : $documents,
            asset : $assets,
            output: $progress->output,
            failed: $progress->failed,
        );

        return new CommandResult(
            command: $command,
            status : $record->complete() ? MessageStatus::Success : MessageStatus::Failure,
            result : $record,
        );
    }

    /**
     * Register one route per document, named by local path and addressed by
     * published path.
     *
     * Registration happens before the first render, so a link to a document the
     * loop has not reached yet still has an address. The collector refuses a
     * duplicate name or path, which is how two local paths that publish at one
     * address are caught: nothing here re-implements that check.
     *
     * @param list<non-empty-string> $documents
     *
     * @throws DuplicateRouteException When two documents publish at one path
     * @throws InvalidArgumentException When a local path does not name a document
     */
    private function seed(array $documents): void
    {
        foreach ($documents as $document) {
            $this->collector->get(
                path      : $this->root->published($document),
                middleware: $this->middleware,
                name      : $document,
            );
        }
    }

    /**
     * Render one document and write it where the translation says it goes.
     *
     * The page is set on the context before conversion and cleared after, in a
     * `finally` so a failed document cannot leave its page behind for the next
     * one. The rewriter reads that page during parsing, which is how a relative
     * link is resolved against the document it was written in.
     *
     * The throws below are what the caller is prepared to record, and they are
     * the reason the loop catches a named union rather than `Throwable`: every
     * member is a failure of the document being processed. An engine error, or
     * an exception from a collaborator this code does not understand, propagates
     * instead of being recorded, because presenting a defect in the builder as a
     * problem with the documentation sends the operator after a file that is
     * fine.
     *
     * This package's own refusals are one member of that union through
     * `ExceptionInterface`, which is what the interface exists for: without it
     * the three types below would each have to be named, and a new one added
     * later would silently stop being recorded.
     *
     * @param non-empty-string $file The path on disk, already resolved for whichever root holds it
     *
     * @throws CommonMarkException When the document cannot be parsed or rendered
     * @throws ConfigurationExceptionInterface When a converter option is invalid
     * @throws ExceptionInterface When a local path, page or link is refused
     * @throws FileWriterException When the output cannot be written
     * @throws PslExceptionInterface When the source cannot be read
     * @throws ContainerExceptionInterface When the page cannot be built, which is
     *         a wiring failure rather than a document failure and aborts the build
     */
    private function write(
        ConverterInterface $converter,
        string $file,
        string $document,
        string $target,
    ): void {
        $this->context->set(Type\instance_of(Page::class)
            ->coerce(
                $this->services->build(Page::class, [
                    'localPath' => $document,
                    'label'     => self::labelFor($document),
                ]),
            ));

        try {
            $html = (string) $converter->convert(File\read($file));
        } finally {
            $this->context->clear();
        }

        FileWriter::writeTree(self::join($target, $this->root->file($document)), $html);
    }
}
