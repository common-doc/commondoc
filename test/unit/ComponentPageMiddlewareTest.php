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
use CommonDoc\Middleware\ComponentPageMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDocTest\Support\AbstractTemporaryTree;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\Route;
use Mezzio\Template\TemplateRendererInterface;
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

/**
 * The pages that belong to no document: a component's own address, and the home page.
 */
#[CoversClass(ComponentPageMiddleware::class)]
#[CoversMethod(ComponentPageMiddleware::class, '__construct')]
#[CoversMethod(ComponentPageMiddleware::class, 'process')]
final class ComponentPageMiddlewareTest extends AbstractTemporaryTree
{
    #[Test]
    public function leavesAnyOtherMessageAlone(): void
    {
        $message = new class implements MessageInterface {};
        $result  = new CommandResult(
            command: $this->command([$this->root(1)]),
            status : MessageStatus::Success,
            result : null,
        );

        $next = $this->createMock(PipelineHandlerInterface::class);
        $next->expects($this->once())->method('handle')->with($message)->willReturn($result);

        static::assertSame($result, $this->subject($this->unusedRenderer())->process($message, $next));
        static::assertFalse(is_file("{$this->tree}/build/comp/index.html"));
    }

    #[Test]
    public function listsAnUnreleasedComponentAsACardWithNoVersions(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer
            ->expects($this->once())
            ->method('render')
            ->with(
                'commondoc::home',
                [
                    'cards' => [
                        ['title' => 'Email', 'latest' => '', 'url' => '/email/', 'versions' => []],
                    ],
                ],
            )
            ->willReturn('<p>home</p>');

        $this->process($this->command([new Root(
            path : '/nowhere/docs',
            mount: '/email/',
        )]), $renderer);

        static::assertFalse(is_file("{$this->tree}/build/email/index.html"));
    }

    #[Test]
    public function neverReplacesADocumentThatPublishesAtTheSiteRoot(): void
    {
        $command = new BuildSiteCommand(
            roots : [$this->root(2)],
            output: "{$this->tree}/build",
            scan  : new Scan(
                sources  : ['index.md' => 'index.md'],
                documents: ['index.md'],
                assets   : [],
            ),
        );

        $this->process($command, $this->unusedRenderer());

        static::assertFalse(is_file("{$this->tree}/build/index.html"));
        static::assertTrue(is_file("{$this->tree}/build/comp/index.html"));
    }

    #[Test]
    public function sendsAComponentsOwnAddressToItsLatestVersion(): void
    {
        $this->process($this->command([$this->root(1), $this->root(2)]), $this->renderer());

        $redirect = (string) file_get_contents("{$this->tree}/build/comp/index.html");

        static::assertStringContainsString('url=/comp/v2/', $redirect);
        static::assertStringContainsString('<link rel="canonical" href="/comp/v2/">', $redirect);
    }

    #[Test]
    public function writesAHomePageWithACardForEachComponent(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer
            ->expects($this->once())
            ->method('render')
            ->with(
                'commondoc::home',
                [
                    'cards' => [
                        [
                            'title'    => 'Comp',
                            'latest'   => 'v2.0.0',
                            'url'      => '/comp/v2/',
                            'versions' => [
                                ['label' => 'v2 (latest)', 'url' => '/comp/v2/', 'current' => true],
                                ['label' => 'v1', 'url' => '/comp/v1/', 'current' => false],
                            ],
                        ],
                    ],
                ],
            )
            ->willReturn('<p>home</p>');

        $this->process($this->command([$this->root(1), $this->root(2)]), $renderer);

        static::assertSame('<p>home</p>', file_get_contents("{$this->tree}/build/index.html"));
    }

    #[Test]
    public function writesNothingForASiteWithoutVersions(): void
    {
        $this->process($this->command([new Root(path: 'docs')]), $this->unusedRenderer());

        static::assertFalse(is_file("{$this->tree}/build/index.html"));
    }

    protected function setUp(): void
    {
        $this->makeTree();
    }

    /**
     * @param list<Root> $roots
     */
    private function command(array $roots): BuildSiteCommand
    {
        return new BuildSiteCommand(
            roots : $roots,
            output: "{$this->tree}/build",
            scan  : new Scan(
                sources  : [],
                documents: [],
                assets   : [],
            ),
        );
    }

    private function process(BuildSiteCommand $command, TemplateRendererInterface $renderer): void
    {
        $result = new CommandResult(
            command: $command,
            status : MessageStatus::Success,
            result : null,
        );

        $next = $this->createStub(PipelineHandlerInterface::class);
        $next->method('handle')->willReturn($result);

        $this->subject($renderer)->process($command, $next);
    }

    private function renderer(): TemplateRendererInterface
    {
        $renderer = $this->createStub(TemplateRendererInterface::class);
        $renderer->method('render')->willReturn('<p>home</p>');

        return $renderer;
    }

    private function root(int $major): Root
    {
        return new Root(
            path   : "/comp/{$major}",
            mount  : "/comp/v{$major}/",
            release: new Release(
                component: new Component('comp'),
                version  : Version::fromTag("v{$major}.0.0") ?? self::fail('The tag is not a version.'),
            ),
        );
    }

    private function subject(TemplateRendererInterface $renderer): ComponentPageMiddleware
    {
        $router = new QueryParamRouter();
        $router->addRoute(new Route(
            path      : '/{path}',
            middleware: new NotServedMiddleware(),
            name      : DocumentRoute::NAME,
        ));

        return new ComponentPageMiddleware(
            route   : new DocumentRoute(new DocumentRoot('/'), new UrlHelper($router)),
            renderer: $renderer,
        );
    }

    private function unusedRenderer(): TemplateRendererInterface
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->never())->method('render');

        return $renderer;
    }
}
