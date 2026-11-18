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
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Record\BuildRecord;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDocTest\Support\AbstractTemporaryTree;
use Laminas\ServiceManager\ServiceLocatorInterface;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Output\RenderedContentInterface;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Webware\MessageBus\MessageStatus;

use function file_get_contents;
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
    public function copiesAnAssetWhereItSits(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/images/d.png', contents: 'PNG');

        $this->handler()->handle($this->command());

        // An asset is not translated, so the output file is its own local path.
        static::assertSame('PNG', (string) file_get_contents("{$this->output}/images/d.png"));
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

        // No special case for `index`: a better label needs a source this build
        // does not have yet, and inventing navigation vocabulary here would hide
        // that rather than state it.
        static::assertSame(
            [
                'guide/setup.md' => 'setup',
                'index.md'       => 'index',
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
    public function refusesTwoDocumentsThatPublishAtOneAddress(): void
    {
        $this->writeFile('source/guide/setup.md', contents: "# Setup\n");
        $this->writeFile('source/guide/setup/index.md', contents: "# Nested\n");

        // Both publish at /guide/setup/, which the collector refuses as a
        // duplicate route. Nothing in this package re-implements that check.
        $this->expectException(DuplicateRouteException::class);

        $this->handler()->handle($this->command());
    }

    #[Test]
    public function registersOneRoutePerDocumentNamedByLocalPath(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/guide/setup.md', contents: "# Setup\n");

        $collector = $this->collector();

        $this->handler(collector: $collector)->handle($this->command());

        $routes = [];

        foreach ($collector->getRoutes() as $route) {
            $routes[$route->getName()] = $route->getPath();
        }

        static::assertSame(
            [
                'guide/setup.md' => '/guide/setup/',
                'index.md'       => '/',
            ],
            $routes,
        );
    }

    #[Test]
    public function reportsTheRecordItProduced(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/guide/setup.md', contents: "# Setup\n");

        $result = $this->handler()->handle($this->command());

        /** @var mixed $raw */
        $raw = $result->getResult();

        static::assertInstanceOf(BuildRecord::class, $raw);
        static::assertSame(MessageStatus::Success, $result->getStatus());
        static::assertSame(['guide/setup.md', 'index.md'], $raw->input);
        static::assertSame(['guide/setup.md', 'index.md'], $raw->output);
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
        return new RouteCollector($this->createStub(RouterInterface::class));
    }

    private function command(): BuildSiteCommand
    {
        return new BuildSiteCommand(
            source: $this->source,
            output: $this->output,
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
     */
    private function handler(
        ?RouteCollectorInterface $collector = null,
        ?ConverterInterface $converter = null,
        ?PageContext $context = null,
        ?Closure $onPage = null,
    ): BuildSiteHandler {
        $root = new DocumentRoot('/');

        if (null === $converter) {
            $converter = $this->createStub(ConverterInterface::class);
            $converter->method('convert')->willReturn(self::render('<p>body</p>'));
        }

        return new BuildSiteHandler(
            root      : $root,
            collector : $collector ?? $this->collector(),
            context   : $context ?? new PageContext(),
            middleware: new NotServedMiddleware(),
            services  : $this->locator($root, $converter, $onPage),
        );
    }

    /**
     * A locator that answers the two `build()` calls the handler makes.
     *
     * `build()` rather than `get()` is the contract under test, so this stub
     * answers only that method: a handler that fell back to `get()` would get
     * null and fail rather than quietly pass.
     *
     * @param ?Closure(string, string): void $onPage Observes the label each page was built with
     */
    private function locator(
        DocumentRoot $root,
        ConverterInterface $converter,
        ?Closure $onPage = null,
    ): ServiceLocatorInterface {
        $locator = $this->createStub(ServiceLocatorInterface::class);
        $locator
            ->method('build')
            ->willReturnCallback(static function (string $id, ?array $options = null) use (
                $root,
                $converter,
                $onPage,
            ): mixed {
                if (ConverterInterface::class === $id) {
                    return $converter;
                }

                /** @var array{localPath: string, label: string} $options */
                $options ??= [];

                $onPage?->__invoke($options['localPath'], $options['label']);

                return new Page(
                    label       : $options['label'],
                    projectName : 'CommonDoc',
                    projectSlug : 'commondoc',
                    sourcePath  : $options['localPath'],
                    url         : $root->published($options['localPath']),
                    versionLabel: '1.0',
                );
            });

        return $locator;
    }
}
