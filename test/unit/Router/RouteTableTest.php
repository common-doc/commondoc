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

namespace CommonDocTest\Router;

use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Router\RouteTable;
use InvalidArgumentException;
use LogicException;
use Mezzio\Router\Route;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RouteTable::class)]
#[CoversClass(NotServedMiddleware::class)]
#[CoversMethod(RouteTable::class, '__construct')]
#[CoversMethod(RouteTable::class, 'add')]
#[CoversMethod(RouteTable::class, 'count')]
#[CoversMethod(RouteTable::class, 'has')]
#[CoversMethod(RouteTable::class, 'options')]
#[CoversMethod(RouteTable::class, 'published')]
#[CoversMethod(RouteTable::class, 'registerRoutes')]
#[CoversMethod(NotServedMiddleware::class, 'process')]
final class RouteTableTest extends TestCase
{
    private static function collector(): RouteCollector
    {
        return new RouteCollector(self::createStub(RouterInterface::class));
    }

    #[Test]
    public function aBuildRouteRefusesToServeARequest(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $uri     = self::createStub(UriInterface::class);

        $uri->method('getPath')->willReturn('/guide/setup/');
        $request->method('getUri')->willReturn($uri);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot serve a request');

        new NotServedMiddleware()->process($request, self::createStub(RequestHandlerInterface::class));
    }

    #[Test]
    public function reAddingTheSameRouteUpdatesItsOptions(): void
    {
        $table = new RouteTable();
        $table->add(
            localPath    : 'a.md',
            publishedPath: '/a/',
        );
        $table->add(
            localPath    : 'a.md',
            publishedPath: '/a/',
            options      : ['label' => 'A'],
        );

        static::assertSame(1, $table->count());
        static::assertSame(['label' => 'A'], $table->options('a.md'));
    }

    #[Test]
    public function recordsAPublishedPathUnderItsLocalPath(): void
    {
        $table = new RouteTable();
        $table->add(
            localPath    : 'guide/setup.md',
            publishedPath: '/guide/setup/',
        );

        static::assertSame(1, $table->count());
        static::assertTrue($table->has('guide/setup.md'));
        static::assertSame('/guide/setup/', $table->published('guide/setup.md'));
    }

    #[Test]
    public function refusesAnEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a local path and a published path');

        new RouteTable()->add(
            localPath    : '',
            publishedPath: '/a/',
        );
    }

    #[Test]
    public function refusesAPublishedPathWithoutALeadingSlash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with a slash');

        new RouteTable()->add(
            localPath    : 'a.md',
            publishedPath: 'a/',
        );
    }

    #[Test]
    public function refusesMovingADocumentToADifferentAddress(): void
    {
        $table = new RouteTable();
        $table->add(
            localPath    : 'a.md',
            publishedPath: '/a/',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot move');

        $table->add(
            localPath    : 'a.md',
            publishedPath: '/elsewhere/',
        );
    }

    #[Test]
    public function refusesTwoDocumentsClaimingOneAddress(): void
    {
        $table = new RouteTable();
        $table->add(
            localPath    : 'a.md',
            publishedPath: '/same/',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('claimed by both');

        $table->add(
            localPath    : 'b.md',
            publishedPath: '/same/',
        );
    }

    #[Test]
    public function registersEveryDocumentInTheTable(): void
    {
        $table     = new RouteTable();
        $collector = self::collector();

        $table->add(
            localPath    : 'a.md',
            publishedPath: '/a/',
        );
        $table->add(
            localPath    : 'b.md',
            publishedPath: '/b/',
        );
        $table->registerRoutes($collector);

        static::assertCount(2, $collector->getRoutes());
    }

    #[Test]
    public function registersTheLocalPathAsTheNameAndThePublishedPathAsThePath(): void
    {
        $table     = new RouteTable();
        $collector = self::collector();

        $table->add(
            localPath    : 'guide/setup.md',
            publishedPath: '/guide/setup/',
            options      : ['label' => 'Setup'],
        );
        $table->registerRoutes($collector);

        $routes = $collector->getRoutes();

        static::assertCount(1, $routes);

        /** @var Route $route */
        $route = $routes[0];

        static::assertSame('guide/setup.md', $route->getName());
        static::assertSame('/guide/setup/', $route->getPath());
        static::assertSame(['label' => 'Setup'], $route->getOptions());
    }

    #[Test]
    public function startsEmpty(): void
    {
        $table = new RouteTable();

        static::assertSame(0, $table->count());
        static::assertFalse($table->has('guide/setup.md'));
        static::assertNull($table->published('guide/setup.md'));
        static::assertSame([], $table->options('guide/setup.md'));
    }

    #[Test]
    public function storesOptionsUnchanged(): void
    {
        $table = new RouteTable();
        $table->add(
            localPath    : 'guide/setup.md',
            publishedPath: '/guide/setup/',
            options      : [
                'label'      => 'Setup',
                'navigation' => 'docs',
                'order'      => 3,
            ],
        );

        static::assertSame(
            ['label' => 'Setup', 'navigation' => 'docs', 'order' => 3],
            $table->options('guide/setup.md'),
        );
    }
}
