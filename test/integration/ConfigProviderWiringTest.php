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

namespace CommonDocTestIntegration;

use Closure;
use CommonDoc\ConfigProvider;
use CommonDoc\Container\ConverterFactoryFactory;
use CommonDoc\Container\DocumentViewRendererFactory;
use CommonDoc\Container\PageFactoryFactory;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use JsonException;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Router\QueryParamRouter;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Guards the wiring contract between composer.json, the aggregated config and
 * this package's namespace.
 *
 * Renaming the namespace but missing `extra.laminas.config-provider` breaks every
 * consumer silently, so the two are asserted to agree. The remaining cases boot
 * the real container, because a provider entry that is never aggregated is only
 * visible that way.
 */
#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, 'getDependencies')]
#[CoversMethod(ConfigProvider::class, 'getViewConfig')]
final class ConfigProviderWiringTest extends TestCase
{
    #[Test]
    public function acceptsARouteDuringABuildWithoutADispatchingRequest(): void
    {
        $collector = $this->container()->get(RouteCollector::class);

        static::assertInstanceOf(RouteCollector::class, $collector);

        $route = $collector->get(
            path      : '/guide/setup/',
            middleware: new NotServedMiddleware(),
            name      : 'guide/setup.md',
        );
        $route->setOptions(['label' => 'Setup']);

        static::assertCount(1, $collector->getRoutes());
        static::assertSame('guide/setup.md', $route->getName());
        static::assertSame('/guide/setup/', $route->getPath());
        static::assertSame(['label' => 'Setup'], $route->getOptions());
    }

    #[Test]
    public function buildsAPageThroughTheContainerWithADerivedPath(): void
    {
        $container = $this->container();

        // The route has to exist before the page can be built, because the page's URL is
        // generated from it rather than computed here. That is the build's own order:
        // the routes are registered before the first page is built.
        $container->get(RouteCollector::class)->route(
            path      : '/guide/setup/',
            middleware: new NotServedMiddleware(),
        );

        $pages = $container->get(PageFactoryFactory::class);

        static::assertInstanceOf(Closure::class, $pages);

        $page = $pages(
            localPath: 'guide/setup.md',
            label    : 'Setup',
        );

        static::assertInstanceOf(Page::class, $page);
        static::assertSame('guide/setup.md', $page->sourcePath);
        // The published path comes from the DocumentRoot the container built, so the
        // page cannot disagree with the file the write pass will produce. The URL is the
        // route's own path, which is why the route above is registered at that same path.
        static::assertSame(
            $container->get(DocumentRoot::class)->published('guide/setup.md'),
            $page->url,
        );
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function composerDeclaresThisPackageAsItsConfigProvider(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/../../composer.json');

        /** @var array{extra: array{laminas: array{'config-provider': string}}} $composer */
        $composer = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        static::assertSame(
            ConfigProvider::class,
            $composer['extra']['laminas']['config-provider'],
        );
    }

    #[Test]
    public function publishesTheTemplateMapAndTheLayout(): void
    {
        /** @var array{templates: array{map: array<string, string>, layout: string}} $config */
        $config = $this->container()->get('config');

        $provider = new ConfigProvider()->getViewConfig();

        static::assertSame($provider['map'], $config['templates']['map']);
        static::assertSame($provider['layout'], $config['templates']['layout']);
    }

    #[Test]
    public function resolvesTheDocumentViewRendererFromItsFactory(): void
    {
        $dependencies = new ConfigProvider()->getDependencies();

        static::assertSame(
            DocumentViewRendererFactory::class,
            $dependencies['factories'][DocumentViewRenderer::class],
        );
        static::assertInstanceOf(
            DocumentViewRenderer::class,
            $this->container()->get(DocumentViewRenderer::class),
        );
    }

    #[Test]
    public function resolvesThePageContextAndItsListener(): void
    {
        $container = $this->container();

        static::assertInstanceOf(PageContext::class, $container->get(PageContext::class));
        static::assertInstanceOf(PageContextListener::class, $container->get(PageContextListener::class));
    }

    #[Test]
    public function resolvesTheRouterAndTheCollectorItFeeds(): void
    {
        $container = $this->container();

        // RouteCollectorFactory refuses to build without a RouterInterface, so
        // resolving the collector is what proves the alias is wired and not only
        // declared.
        static::assertInstanceOf(QueryParamRouter::class, $container->get(RouterInterface::class));

        $collector = $container->get(RouteCollector::class);

        static::assertInstanceOf(RouteCollector::class, $collector);
        static::assertSame($collector, $container->get(RouteCollectorInterface::class));
    }

    #[Test]
    public function servesThePageAndConverterFunctionsUnderTheirFactoryNames(): void
    {
        $container = $this->container();

        // What the container serves under these names is a callable, not an instance of the
        // factory: the handler is handed functions and never a container.
        static::assertInstanceOf(Closure::class, $container->get(ConverterFactoryFactory::class));
        static::assertInstanceOf(Closure::class, $container->get(PageFactoryFactory::class));
    }

    private function container(): ServiceManager
    {
        return require __DIR__ . '/../../config/container.php';
    }
}
