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
use CommonDoc\Output\DocumentRoutes;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Registration is the one place a document becomes a route, so what the route carries
 * is asserted here rather than through a whole build.
 */
#[CoversClass(DocumentRoutes::class)]
#[CoversMethod(DocumentRoutes::class, '__construct')]
#[CoversMethod(DocumentRoutes::class, 'labelFor')]
#[CoversMethod(DocumentRoutes::class, 'register')]
final class DocumentRoutesTest extends TestCase
{
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
                '/guide/setup/' => ['navigation' => DocumentRoutes::NAVIGATION, 'label' => 'setup', 'order' => 0],
                '/'             => ['navigation' => DocumentRoutes::NAVIGATION, 'label' => 'index', 'order' => 1],
            ],
            $options,
        );
    }

    #[Test]
    public function derivesALabelFromTheFileNameWithNoSpecialCaseForIndex(): void
    {
        // A better label needs a source this build does not have yet, so every index page
        // is labelled `index` and that is stated rather than hidden.
        static::assertSame('setup', DocumentRoutes::labelFor('guide/setup.md'));
        static::assertSame('index', DocumentRoutes::labelFor('index.md'));
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

        static::assertSame(['/guide/setup/', '/'], $paths);
    }

    private function collector(): RouteCollector
    {
        return new RouteCollector($this->createStub(RouterInterface::class));
    }

    private function routes(RouteCollector $collector): DocumentRoutes
    {
        return new DocumentRoutes(
            root      : new DocumentRoot('/'),
            collector : $collector,
            middleware: new NotServedMiddleware(),
        );
    }
}
