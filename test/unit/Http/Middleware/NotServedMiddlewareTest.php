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

namespace CommonDocTest\Http\Middleware;

use CommonDoc\Http\Middleware\NotServedMiddleware;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(NotServedMiddleware::class)]
#[CoversMethod(NotServedMiddleware::class, 'process')]
final class NotServedMiddlewareTest extends TestCase
{
    private static function requestFor(string $path): ServerRequestInterface
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = self::createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    #[Test]
    public function refusesARequestAndNamesTheRouteItRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The route "/guide/setup/" was registered by a documentation build and cannot serve a request.',
        );

        new NotServedMiddleware()->process(
            self::requestFor('/guide/setup/'),
            self::createStub(RequestHandlerInterface::class),
        );
    }

    #[Test]
    public function refusesWithoutCallingTheHandler(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $this->expectException(LogicException::class);

        new NotServedMiddleware()->process(self::requestFor('/'), $handler);
    }
}
