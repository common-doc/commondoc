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

namespace CommonDocTest\Http;

use CommonDoc\Exception\HttpException;
use CommonDoc\Http\ResponseBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use RuntimeException;

/**
 * Assembling a response from what a transfer produced.
 *
 * This is the half of the transport that needs no network, so it is where the header map and the
 * failure path are pinned. A server controls every value that goes in, which is why nothing may
 * escape from here as an arbitrary exception type.
 */
#[CoversClass(ResponseBuilder::class)]
#[CoversMethod(ResponseBuilder::class, '__construct')]
#[CoversMethod(ResponseBuilder::class, 'build')]
final class ResponseBuilderTest extends TestCase
{
    #[Test]
    public function addsEveryHeaderValue(): void
    {
        $response = new ResponseBuilder()->build(
            status : 200,
            headers: [
                'set-cookie'   => ['a=1', 'b=2'],
                'content-type' => ['text/html'],
            ],
            body   : '',
        );

        // A header that repeats keeps both values, and the name arrives lowercased because that is
        // how the collector stores it.
        static::assertSame(['a=1', 'b=2'], $response->getHeader('set-cookie'));
        static::assertSame(['text/html'], $response->getHeader('Content-Type'));
    }

    #[Test]
    public function carriesTheStatus(): void
    {
        $response = new ResponseBuilder()->build(
            status : 404,
            headers: [],
            body   : '',
        );

        static::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function keepsTheCauseWhenItRefuses(): void
    {
        $cause   = new RuntimeException('the factory refused');
        $factory = $this->createStub(ResponseFactoryInterface::class);
        $factory->method('createResponse')->willThrowException($cause);

        try {
            new ResponseBuilder(responseFactory: $factory)->build(
                status : 200,
                headers: [],
                body   : '',
            );
        } catch (HttpException $exception) {
            static::assertSame($cause, $exception->getPrevious());

            return;
        }

        static::fail('The builder did not refuse a factory that failed.');
    }

    #[Test]
    public function refusesAHeaderTheMessageWillNotTake(): void
    {
        // A space is not valid in an HTTP header name, and the message implementation rejects it.
        // That must arrive as this package's exception rather than as the implementation's own,
        // because the value is server-controlled.
        $this->expectException(HttpException::class);

        new ResponseBuilder()->build(
            status : 200,
            headers: ['not a header name' => ['value']],
            body   : '',
        );
    }

    #[Test]
    public function refusesWhenTheFactoryItselfFails(): void
    {
        $factory = $this->createStub(ResponseFactoryInterface::class);
        $factory
            ->method('createResponse')
            ->willThrowException(new RuntimeException('the factory refused'));

        $this->expectException(HttpException::class);

        new ResponseBuilder(responseFactory: $factory)->build(
            status : 200,
            headers: [],
            body   : '',
        );
    }

    #[Test]
    public function writesTheBodyAndRewindsSoTheCallerCanReadIt(): void
    {
        $response = new ResponseBuilder()->build(
            status : 200,
            headers: [],
            body   : 'hello',
        );

        // The position is asserted first, because __toString() rewinds for itself and would
        // leave the stream at its end. Only an explicit rewind in the builder leaves it at zero.
        static::assertSame(0, $response->getBody()->tell());
        static::assertSame('hello', (string) $response->getBody());
    }
}
