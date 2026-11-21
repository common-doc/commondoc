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

namespace CommonDocTest\Output;

use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\Output\RouteRegistrar;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\RouteCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Router\QueryParamRouter;

/**
 * Registration is the one place a document becomes a route, so what the route carries
 * is asserted here rather than through a whole build.
 */
#[CoversClass(RouteRegistrar::class)]
#[CoversMethod(RouteRegistrar::class, '__construct')]
#[CoversMethod(RouteRegistrar::class, 'labelFor')]
#[CoversMethod(RouteRegistrar::class, 'register')]
final class RouteRegistrarTest extends TestCase
{
    private ?QueryParamRouter $router = null;

    #[Test]
    public function carriesTheOptionsTheNavigationHelperReads(): void
    {
        $collector = $this->collector();

        $this->routes($collector)->register(['guide/setup.md', 'index.md']);

        $options = [];

        foreach ($collector->getRoutes() as $route) {
            $options[$route->getPath()] = $route->getOptions();
        }

        static::assertSame(
            [
                '/{path}'       => [],
                '/guide/setup/' => ['navigation' => RouteRegistrar::NAVIGATION, 'label' => 'setup', 'order' => 0],
                '/'             => ['navigation' => RouteRegistrar::NAVIGATION, 'label' => 'index', 'order' => 1],
            ],
            $options,
        );
    }

    #[Test]
    public function derivesALabelFromTheFileNameWithNoSpecialCaseForIndex(): void
    {
        // A better label needs a source this build does not have yet, so every index page
        // is labelled `index` and that is stated rather than hidden.
        static::assertSame('setup', RouteRegistrar::labelFor('guide/setup.md'));
        static::assertSame('index', RouteRegistrar::labelFor('index.md'));
    }

    #[Test]
    public function refusesTwoDocumentsThatPublishAtOnePath(): void
    {
        // Both publish at /guide/setup/, which the collector refuses as a duplicate
        // route. Nothing in this package re-implements that check.
        $this->expectException(DuplicateRouteException::class);

        $this->routes($this->collector())->register(['guide/setup.md', 'guide/setup/index.md']);
    }

    #[Test]
    public function registersOneRoutePerDocumentAtItsPublishedPath(): void
    {
        $collector = $this->collector();

        $this->routes($collector)->register(['guide/setup.md', 'index.md']);

        // The path is asserted and the name is not: the router derives the name from the
        // path, so a literal here would be the router's rule restated.
        $paths = [];

        foreach ($collector->getRoutes() as $route) {
            $paths[] = $route->getPath();
        }

        static::assertSame(['/{path}', '/guide/setup/', '/'], $paths);
    }

    #[Test]
    public function registersThePatternRouteWithoutNavigationOptions(): void
    {
        $collector = $this->collector();

        $this->routes($collector)->register(['index.md']);

        $pattern = $collector->getRoutes()[0];

        static::assertSame(DocumentRoute::NAME, $pattern->getName());
        static::assertSame([], $pattern->getOptions());
    }

    private function collector(): RouteCollector
    {
        $this->router = new QueryParamRouter();

        return new RouteCollector($this->router);
    }

    private function routes(RouteCollector $collector): RouteRegistrar
    {
        $root = new DocumentRoot('/');

        return new RouteRegistrar(
            route     : new DocumentRoute($root, new UrlHelper($this->router ?? new QueryParamRouter())),
            collector : $collector,
            middleware: new NotServedMiddleware(),
        );
    }
}
