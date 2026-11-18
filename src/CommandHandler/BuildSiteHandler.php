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

use Closure;
use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Input\Asset;
use CommonDoc\Input\Markdown;
use CommonDoc\Input\Tree;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Record\BuildRecord;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use InvalidArgumentException;
use League\CommonMark\ConverterInterface;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\RouteCollectorInterface;
use Psl\Exception\ExceptionInterface as PslExceptionInterface;
use Psl\File;
use Psl\Type;
use Throwable;
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
 * One converter per build. The callable this handler is given builds a fresh
 * environment with a link rewriter bound to this build's own document list, so
 * two builds in one process cannot see each other's listeners.
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
     * @param Closure $converter Builds the converter for one build, bound to
     *        that build's document and asset lists
     * @param Closure $page Builds the page for one document
     */
    // @mago-expect lint:excessive-parameter-list - a coordinator names each collaborator it drives.
    public function __construct(
        private DocumentRoot $root,
        private RouteCollectorInterface $collector,
        private PageContext $context,
        private NotServedMiddleware $middleware,
        private Closure $converter,
        private Closure $page,
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
     * The reason a document was not written, never empty.
     *
     * `BuildRecord` requires a non-empty reason, so an exception with no message
     * is described by its own class rather than by an empty string that would
     * read as though nothing went wrong.
     *
     * @return non-empty-string
     */
    private static function reason(Throwable $failure): string
    {
        $message = $failure->getMessage();

        if ('' === $message) {
            return $failure::class;
        }

        return $message;
    }

    /**
     * Build the site and report what was written and what failed.
     *
     * @throws InvalidArgumentException When the source root is not a readable directory
     * @throws DuplicateRouteException When two documents publish at one path
     * @throws FileWriterException When an asset cannot be copied into the output
     * @throws PslExceptionInterface When an asset cannot be read from the source
     */
    public function handle(BuildSiteCommand $command): CommandResultInterface
    {
        $source = $command->getSource();
        $target = $command->getOutput();

        $paths     = Tree::at($source)->paths();
        $documents = new Markdown()->scan($paths);
        $assets    = new Asset()->scan(
            paths    : $paths,
            documents: $documents,
        );

        $this->seed($documents);

        // Coerced because a closure carries no signature at the call site: both
        // factories are asked for a `Closure`, so this is where the result is
        // narrowed back to the type the build actually needs.
        $converter = Type\instance_of(ConverterInterface::class)
            ->coerce(($this->converter)($documents, $assets));

        $written = [];
        $failed  = [];

        foreach ($documents as $document) {
            try {
                $this->write($converter, $source, $document, $target);
                $written[] = $document;
            } catch (Throwable $failure) {
                $failed[$document] = self::reason($failure);
            }
        }

        foreach ($assets as $asset) {
            $this->copy($source, $asset, $target);
        }

        $record = new BuildRecord(
            input : $documents,
            output: $written,
            failed: $failed,
        );

        return new CommandResult(
            command: $command,
            status : $record->complete() ? MessageStatus::Success : MessageStatus::Failure,
            result : $record,
        );
    }

    /**
     * Copy one asset into the output at the path it already has.
     *
     * An asset is not translated. It sits where it is published, so its output
     * file is its own local path; `DocumentRoot::asset()` exists for the address
     * a link renders, not for the file. The write goes through `FileWriter` so
     * the copy is atomic, exactly as a document's is.
     *
     * @throws FileWriterException When the asset cannot be written
     * @throws PslExceptionInterface When the asset cannot be read
     */
    private function copy(string $source, string $asset, string $target): void
    {
        FileWriter::writeTree(
            self::join($target, $asset),
            File\read(self::join($source, $asset)),
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
     * Any failure is declared rather than enumerated: this is the boundary where
     * one document must not abort the build, so the contract is that anything may
     * fail here and the caller records it.
     *
     * @throws Throwable
     */
    private function write(
        ConverterInterface $converter,
        string $source,
        string $document,
        string $target,
    ): void {
        $this->context->set(Type\instance_of(Page::class)
            ->coerce(
                ($this->page)($document, self::labelFor($document)),
            ));

        try {
            $html = (string) $converter->convert(File\read(self::join($source, $document)));
        } finally {
            $this->context->clear();
        }

        FileWriter::writeTree(self::join($target, $this->root->file($document)), $html);
    }
}
