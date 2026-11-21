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

namespace CommonDocTest\Container;

use Closure;
use CommonDoc\Container\ConverterFactoryFactory;
use CommonDoc\Container\ExtensionPluginManager;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\HeadingListener;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use League\CommonMark\ConverterInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\Route;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Webware\Router\QueryParamRouter;

/**
 * The function a build calls to get its converter.
 *
 * What the container serves under this factory's name is a callable, and its behaviour is
 * told apart by what it does to a link: a converter built for a scan rewrites a target
 * the scan contains and refuses one it does not. The assertions are on the output rather
 * than on identity, because a fresh environment that happened to be empty would pass an
 * identity check too.
 */
#[CoversClass(ConverterFactoryFactory::class)]
#[CoversMethod(ConverterFactoryFactory::class, '__invoke')]
final class ConverterFactoryFactoryTest extends TestCase
{
    private int $containerReads = 0;

    /**
     * The real helper over the real router, with one route for the document the tests link
     * to, registered the way a build registers one: at its published path, with no name.
     */
    private static function url(DocumentRoot $root): DocumentRoute
    {
        $middleware = new class implements MiddlewareInterface {
            #[Override]
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $handler->handle($request);
            }
        };

        $router = new QueryParamRouter();
        $router->addRoute(new Route(
            path      : "{$root->basePath}{path}",
            middleware: $middleware,
            name      : DocumentRoute::NAME,
        ));

        return new DocumentRoute($root, new UrlHelper($router));
    }

    #[Test]
    public function buildsAFreshConverterEachTimeItIsCalled(): void
    {
        $converters = $this->converters();

        static::assertNotSame(
            $converters(['guide/intro.md'], []),
            $converters(['guide/intro.md'], []),
        );
    }

    #[Test]
    public function keepsOneBuildsListsOutOfTheNext(): void
    {
        $converters = $this->converters();

        $first  = $converters(['guide/intro.md'], []);
        $second = $converters(['guide/setup.md'], []);

        static::assertStringContainsString('/guide/intro/', (string) $first->convert('[intro](intro.md)'));

        // The second build's lists do not contain the target, so the first build's
        // rewriter must not have leaked into it.
        $this->expectException(RuntimeException::class);

        $second->convert('[intro](intro.md)');
    }

    #[Test]
    public function neverReachesBackIntoTheContainerOnceItIsBuilt(): void
    {
        $converters = $this->converters();
        $reads      = $this->containerReads;

        $converters(['guide/intro.md'], [])->convert('[intro](intro.md)');
        $converters(['guide/intro.md'], [])->convert('[intro](intro.md)');

        // Every collaborator was resolved when the function was made, so calling it any
        // number of times asks the container for nothing.
        static::assertSame($reads, $this->containerReads);
    }

    #[Test]
    public function refusesALinkTheScanDoesNotContain(): void
    {
        $converter = $this->converters()(['guide/setup.md'], []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('which resolves to "guide/intro.md"');

        $converter->convert('[intro](intro.md)');
    }

    #[Test]
    public function rewritesALinkToADocumentTheScanContains(): void
    {
        $converter = $this->converters()(['guide/intro.md'], []);

        static::assertStringContainsString('/guide/intro/', (string) $converter->convert('[intro](intro.md)'));
    }

    /**
     * The function the container would serve for this factory's name.
     *
     * @return Closure(list<non-empty-string> $documents, list<non-empty-string> $assets): ConverterInterface
     */
    private function converters(): Closure
    {
        // The page is what a relative target resolves against, and it reaches the
        // document on the parsed event, exactly as in the application.
        $context = new PageContext();
        $context->set(new Page(
            label       : 'Setup',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'guide/setup.md',
            url         : '/guide/setup/',
            versionLabel: '1.0',
            routeName   : '/guide/setup/',
        ));

        $templates = $this->createStub(TemplateRendererInterface::class);
        $templates
            ->method('render')
            ->willReturnCallback(static fn(string $name, array $params): string => (string) $params['documentBody']);

        $root     = new DocumentRoot('/');
        $services = [
            'config'                      => [],
            ExtensionPluginManager::class => new ExtensionPluginManager(
                $this->createStub(ContainerInterface::class),
                [],
            ),
            DocumentViewRenderer::class   => new DocumentViewRenderer($templates),
            PageContextListener::class    => new PageContextListener($context),
            HeadingListener::class        => new HeadingListener($context),
            DocumentRoot::class           => $root,
            DocumentRoute::class          => self::url($root),
        ];

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(function (string $id) use ($services): mixed {
                ++$this->containerReads;

                return $services[$id] ?? null;
            });

        /** @var Closure(list<non-empty-string> $documents, list<non-empty-string> $assets): ConverterInterface */
        return new ConverterFactoryFactory()($container, ConverterFactoryFactory::class);
    }
}
