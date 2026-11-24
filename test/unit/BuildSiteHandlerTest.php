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

namespace CommonDocTest;

use Closure;
use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\Exception\RuntimeException;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Input\Root;
use CommonDoc\Input\Scan;
use CommonDoc\Input\Source;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\Output\RouteProvider;
use CommonDoc\Record\BuildRecord;
use CommonDoc\Record\Progress;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDocTest\Support\AbstractTemporaryTree;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Output\RenderedContentInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use TypeError;
use Webware\MessageBus\MessageStatus;
use Webware\Router\QueryParamRouter;

use function is_file;
use function mkdir;
use function str_contains;

/**
 * The coordinator, driven over a real tree.
 *
 * The scans touch the filesystem, so a fake one would test a different program.
 * Everything above the scans is a double: the converter is a stub, because what
 * a document renders as belongs to the view tests, not here.
 */
#[CoversClass(BuildSiteHandler::class)]
#[CoversMethod(BuildSiteHandler::class, '__construct')]
#[CoversMethod(BuildSiteHandler::class, 'handle')]
final class BuildSiteHandlerTest extends AbstractTemporaryTree
{
    private string $source = '';
    private string $output = '';

    private ?QueryParamRouter $router = null;

    /**
     * The function the handler calls to get the page for one document.
     *
     * Passed in rather than resolved, which is the contract under test: a handler that
     * reached for anything else to build a page has nothing to reach for here.
     *
     * @param ?Closure(string, string): void $onPage Observes the label each page was built with
     *
     * @return Closure(non-empty-string $localPath, string $label, ?Source $source, ?string $repositoryPath): Page
     */
    private static function pages(DocumentRoute $route, ?Closure $onPage = null): Closure
    {
        return static function (
            string $localPath,
            string $label,
            ?Source $source = null,
            ?string $repositoryPath = null,
        ) use ($route, $onPage): Page {
            if (null !== $onPage) {
                $onPage($localPath, $label);
            }

            return new Page(
                label         : $label,
                projectName   : 'CommonDoc',
                projectSlug   : 'commondoc',
                sourcePath    : $localPath,
                url           : $route->url($localPath),
                versionLabel  : '1.0',
                routeName     : $route->url($localPath),
                source        : $source,
                repositoryPath: $repositoryPath,
            );
        };
    }

    private static function render(string $html): RenderedContentInterface
    {
        return new class($html) implements RenderedContentInterface {
            public function __construct(
                private readonly string $html,
            ) {}

            public function getContent(): string
            {
                return $this->html;
            }

            public function getDocument(): Document
            {
                return new Document();
            }

            public function __toString(): string
            {
                return $this->html;
            }
        };
    }

    #[Test]
    public function buildsOneConverterPerBuildFromWhatTheScanFound(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/guide/setup.md', contents: "# Setup\n");
        $this->writeFile('source/site.css', contents: 'body{}');

        $calls = [];

        $this->handler(onConverter: static function (array $documents, array $assets) use (&$calls): void {
            $calls[] = [$documents, $assets];
        })->handle($this->command(new Progress(output: ['site.css'])));

        // One converter for the build, bound to the build's own lists: the link
        // rewriter inside it is what checks a target against them.
        static::assertSame([[['guide/setup.md', 'index.md'], ['site.css']]], $calls);
    }

    #[Test]
    public function derivesThePageLabelFromTheDocumentPath(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/guide/setup.md', contents: "# Setup\n");

        $labels = [];

        $this->handler(onPage: static function (string $path, string $label) use (&$labels): void {
            $labels[$path] = $label;
        })->handle($this->command());

        // The label is the file name normalised for display. `index` keeps its own
        // label here: it is the title that drops it, because the page's address
        // already names the directory.
        static::assertSame(
            [
                'guide/setup.md' => 'Setup',
                'index.md'       => 'Index',
            ],
            $labels,
        );
    }

    #[Test]
    public function describesAFailureWithNoMessageByItsClass(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");

        $result = $this->handler(converter: $this->failingConverter('Home', message: ''))
            ->handle($this->command());

        /** @var mixed $raw */
        $raw = $result->getResult();

        // `BuildRecord` requires a non-empty reason, and an empty string would
        // read as though nothing went wrong.
        static::assertInstanceOf(BuildRecord::class, $raw);
        static::assertSame(RuntimeException::class, $raw->reasonFor('index.md'));
    }

    #[Test]
    public function leavesNoPageBehindAfterTheLastDocument(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");

        $context = new PageContext();
        $this->handler(context: $context)->handle($this->command());

        static::assertNull($context->current());
    }

    #[Test]
    public function letsAnEngineErrorPropagateRatherThanRecordingIt(): void
    {
        // A TypeError is a bug in this program, not a problem with a document.
        // Recording it as a per-file failure would report a broken build and
        // blame the content, so it must escape the per-document boundary.
        $this->writeFile('source/index.md', contents: "# Home\n");

        $converter = $this->createStub(ConverterInterface::class);
        $converter
            ->method('convert')
            ->willThrowException(
                new TypeError('a defect, not a content problem'),
            );

        $this->expectException(TypeError::class);

        $this->handler(converter: $converter)->handle($this->command());
    }

    #[Test]
    public function recordsADocumentThatFailedAndWritesTheRest(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/guide/setup.md', contents: 'see [gone](missing.md)');

        $result = $this->handler(converter: $this->failingConverter('missing.md'))
            ->handle($this->command());

        /** @var mixed $raw */
        $raw = $result->getResult();

        static::assertInstanceOf(BuildRecord::class, $raw);
        static::assertSame(MessageStatus::Failure, $result->getStatus());
        static::assertSame(['index.md'], $raw->output);
        static::assertSame(['guide/setup.md'], $raw->failedPath());
        static::assertTrue(is_file("{$this->output}/index.html"));
        static::assertFalse(is_file("{$this->output}/guide/setup/index.html"));
    }

    #[Test]
    public function reportsADocumentFailureAndAnAssetFailureInOneRecord(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/guide/setup.md', contents: 'see [gone](missing.md)');
        $this->writeFile('source/site.css', contents: 'body{}');

        // The asset copy is another phase, so its failure arrives on the message
        // rather than from the handler's own work. The handler's job is the one
        // record, not the copying.
        $result = $this->handler(converter: $this->failingConverter('missing.md'))
            ->handle($this->command(new Progress(failed: ['site.css' => 'disk full'])));

        /** @var mixed $raw */
        $raw = $result->getResult();

        static::assertInstanceOf(BuildRecord::class, $raw);
        // Both kinds in one list, ordered by path, because an operator reads one
        // report and should not have to know which kind of file failed.
        static::assertSame(['guide/setup.md', 'site.css'], $raw->failedPath());
    }

    #[Test]
    public function reportsTheRecordItProduced(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/guide/setup.md', contents: "# Setup\n");
        $this->writeFile('source/images/d.png', contents: 'PNG');

        $result = $this->handler()->handle($this->command(new Progress(output: ['images/d.png'])));

        /** @var mixed $raw */
        $raw = $result->getResult();

        static::assertInstanceOf(BuildRecord::class, $raw);
        static::assertSame(MessageStatus::Success, $result->getStatus());
        static::assertSame(['guide/setup.md', 'index.md'], $raw->input);
        static::assertSame(['images/d.png'], $raw->asset);
        static::assertSame(['guide/setup.md', 'images/d.png', 'index.md'], $raw->output);
        static::assertSame([], $raw->failed);
        static::assertTrue($raw->complete());
    }

    #[Test]
    public function writesEveryDocumentAtThePathTheTranslationGives(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/guide/setup.md', contents: "# Setup\n");

        $this->handler()->handle($this->command());

        static::assertTrue(is_file("{$this->output}/index.html"));
        static::assertTrue(is_file("{$this->output}/guide/setup/index.html"));
    }

    protected function setUp(): void
    {
        $this->makeTree();

        $this->source = "{$this->tree}/source";
        $this->output = "{$this->tree}/build";

        mkdir($this->source, recursive: true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree();
    }

    private function collector(): RouteCollectorInterface
    {
        $this->router ??= new QueryParamRouter();

        return new RouteCollector($this->router);
    }

    private function command(?Progress $progress = null): BuildSiteCommand
    {
        $roots = [new Root(path: $this->source)];

        return new BuildSiteCommand(
            roots   : $roots,
            output  : $this->output,
            scan    : Scan::of($roots),
            progress: $progress,
        );
    }

    /**
     * A converter that renders every document, except the one whose source
     * contains the given marker, which fails as a broken link would.
     */
    private function failingConverter(
        string $marker,
        string $message = 'The link target does not exist.',
    ): ConverterInterface {
        $converter = $this->createStub(ConverterInterface::class);
        $converter
            ->method('convert')
            ->willReturnCallback(static function (string $markdown) use ($marker, $message): RenderedContentInterface {
                if (str_contains($markdown, $marker)) {
                    throw new RuntimeException($message);
                }

                return self::render("<p>{$markdown}</p>");
            });

        return $converter;
    }

    /**
     * @param ?Closure(string, string): void $onPage Observes the label each page was built with
     * @param ?Closure(list<non-empty-string>, list<non-empty-string>): void $onConverter Observes the lists the converter was built for
     */
    private function handler(
        ?RouteCollectorInterface $collector = null,
        ?ConverterInterface $converter = null,
        ?PageContext $context = null,
        ?Closure $onPage = null,
        ?Closure $onConverter = null,
    ): BuildSiteHandler {
        $root         = new DocumentRoot('/');
        $this->router ??= new QueryParamRouter();
        $collector    ??= $this->collector();
        $route        = new DocumentRoute($root, new UrlHelper($this->router));

        if (null === $converter) {
            $converter = $this->createStub(ConverterInterface::class);
            $converter->method('convert')->willReturn(self::render('<p>body</p>'));
        }

        return new BuildSiteHandler(
            routes    : new RouteProvider(
                route     : $route,
                collector : $collector,
                middleware: new NotServedMiddleware(),
            ),
            route     : $route,
            context   : $context ?? new PageContext(),
            converters: static function (array $documents, array $assets) use (
                $converter,
                $onConverter,
            ): ConverterInterface {
                if (null !== $onConverter) {
                    $onConverter($documents, $assets);
                }

                return $converter;
            },
            pages     : self::pages($route, $onPage),
        );
    }
}
