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

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Input\Component;
use CommonDoc\Input\Release;
use CommonDoc\Input\Root;
use CommonDoc\Input\Scan;
use CommonDoc\Input\Version;
use CommonDoc\Middleware\SearchIndexMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDocTest\Support\AbstractTemporaryTree;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MessageStatus;
use Webware\MessageBus\PipelineHandlerInterface;
use Webware\Router\QueryParamRouter;

use function file_get_contents;
use function is_file;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * The index is read from the pages the build wrote, so these tests write pages and read the file.
 */
#[CoversClass(SearchIndexMiddleware::class)]
#[CoversMethod(SearchIndexMiddleware::class, '__construct')]
#[CoversMethod(SearchIndexMiddleware::class, 'process')]
final class SearchIndexMiddlewareTest extends AbstractTemporaryTree
{
    private const string BODY = '<main class="document"><h1 id="t">Handlers</h1><p>Handle things.</p></main>';

    #[Test]
    public function indexesEveryPublishedDocumentWithItsComponentAndVersion(): void
    {
        $this->writeFile('build/comp/v2/handlers/index.html', self::BODY);

        $this->process($this->command(['comp/v2/handlers.md']));

        /** @var array{version: int, pages: list<array<string, mixed>>} $index */
        $index = json_decode(
            (string) file_get_contents("{$this->tree}/build/search-index.json"),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        static::assertSame(1, $index['version']);
        static::assertCount(1, $index['pages']);
        static::assertSame('/comp/v2/handlers/', $index['pages'][0]['url']);
        static::assertSame('comp/v2', $index['pages'][0]['section']);
        static::assertSame('Handlers', $index['pages'][0]['title']);
    }

    #[Test]
    public function leavesAnyOtherMessageAlone(): void
    {
        $message = new class implements MessageInterface {};
        $result  = new CommandResult(
            command: $this->command([]),
            status : MessageStatus::Success,
            result : null,
        );

        $next = $this->createMock(PipelineHandlerInterface::class);
        $next->expects($this->once())->method('handle')->with($message)->willReturn($result);

        static::assertSame($result, $this->subject()->process($message, $next));
        static::assertFalse(is_file("{$this->tree}/build/search-index.json"));
    }

    #[Test]
    public function leavesOutADocumentThatWasNotWritten(): void
    {
        $this->process($this->command(['comp/v2/missing.md']));

        $index = json_decode(
            (string) file_get_contents("{$this->tree}/build/search-index.json"),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        static::assertSame([], $index['pages'] ?? null);
    }

    #[Test]
    public function leavesOutAPageThatHasNoDocumentBody(): void
    {
        $this->writeFile('build/comp/v2/handlers/index.html', contents: '<html>Redirecting</html>');

        $this->process($this->command(['comp/v2/handlers.md']));

        $index = json_decode(
            (string) file_get_contents("{$this->tree}/build/search-index.json"),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        static::assertSame([], $index['pages'] ?? null);
    }

    protected function setUp(): void
    {
        $this->makeTree();
    }

    /**
     * @param list<non-empty-string> $documents
     */
    private function command(array $documents): BuildSiteCommand
    {
        $sources = [];

        foreach ($documents as $document) {
            $sources[$document] = $document;
        }

        return new BuildSiteCommand(
            roots : [
                new Root(
                    path   : '/nowhere/comp',
                    mount  : '/comp/v2/',
                    release: new Release(
                        component: new Component('comp'),
                        version  : Version::fromTag('v2.0.0') ?? self::fail('The tag is not a version.'),
                    ),
                ),
            ],
            output: "{$this->tree}/build",
            scan  : new Scan(
                sources  : $sources,
                documents: $documents,
                assets   : [],
            ),
        );
    }

    private function process(BuildSiteCommand $command): void
    {
        $next = $this->createStub(PipelineHandlerInterface::class);
        $next->method('handle')->willReturn(new CommandResult(
            command: $command,
            status : MessageStatus::Success,
            result : null,
        ));

        $this->subject()->process($command, $next);
    }

    private function subject(): SearchIndexMiddleware
    {
        $router = new QueryParamRouter();
        $router->addRoute(new Route(
            path      : '/{path}',
            middleware: new NotServedMiddleware(),
            name      : DocumentRoute::NAME,
        ));

        return new SearchIndexMiddleware(new DocumentRoute(new DocumentRoot('/'), new UrlHelper($router)));
    }
}
