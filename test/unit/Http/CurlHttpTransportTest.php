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
use CommonDoc\Http\CurlHttpTransport;
use CommonDocTest\Support\HttpServerFixture;
use CommonDocTest\Support\LoopbackHttpServer;
use CommonDocTest\Support\UnsafeRequest;
use CommonDocTest\Support\UnsafeUri;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function array_map;
use function sprintf;
use function str_repeat;
use function str_starts_with;
use function strlen;

#[CoversClass(CurlHttpTransport::class)]
#[CoversMethod(CurlHttpTransport::class, 'sendRequest')]
final class CurlHttpTransportTest extends TestCase
{
    /**
     * Nothing listens on port 1, so a request that survives validation fails at connect time.
     */
    private const string CLOSED_PORT = 'http://127.0.0.1:1/';

    /**
     * The length the transport truncates a description at, mirrored here so the boundary is tested.
     */
    private const int DESCRIPTION_LIMIT = 64;

    #[Test]
    public function aDescriptionOfExactlyTheLimitIsNotTruncated(): void
    {
        $visible = str_repeat('a', times: self::DESCRIPTION_LIMIT - strlen(self::CLOSED_PORT) - 2);

        $message = $this->rejectionOf(self::CLOSED_PORT . "{$visible}\r", [])->getMessage();

        $this->assertStringContainsString(self::CLOSED_PORT . "{$visible}\\r", $message);
        $this->assertStringNotContainsString('...', $message);
    }

    #[Test]
    public function aLongUrlIsTruncatedInTheRejection(): void
    {
        $padding = str_repeat('a', times: 120);
        $visible = str_repeat('a', times: self::DESCRIPTION_LIMIT - strlen(self::CLOSED_PORT));

        $message = $this->rejectionOf(self::CLOSED_PORT . "{$padding}\r", [])->getMessage();

        $this->assertStringContainsString(self::CLOSED_PORT . "{$visible}...", $message);
        $this->assertStringNotContainsString($padding, $message);
    }

    #[Test]
    public function aUrlCarryingAControlCharacterIsRejected(): void
    {
        $exception = $this->rejectionOf("http://127.0.0.1:1/\r\nX-Injected: yes", []);

        $message = $exception->getMessage();

        $this->assertStringContainsString('127.0.0.1', $message);
        $this->assertStringNotContainsString("\r", $message);
        $this->assertStringNotContainsString("\n", $message);
        $this->assertStringNotContainsString('...', $message);
    }

    /**
     * A header value that survives validation must reach the wire byte for byte.
     *
     * The listener answers with what it received, so the request is never ended by the client's own
     * timeout: a mutant that dropped the options would still be answered, and would fail on the
     * headers rather than hang.
     */
    #[Test]
    public function cleanHeadersReachTheWireUnchanged(): void
    {
        $sent = $this->sentBytes(
            new RequestFactory()
                ->createRequest(
                    method: 'GET',
                    uri   : 'https://example.test/',
                )
                ->withHeader('Accept', value: 'application/vnd.github+json')
                ->withHeader('X-Trace-Id', value: 'trace-123'),
        );

        $this->assertStringContainsString("Accept: application/vnd.github+json\r\n", $sent);
        $this->assertStringContainsString("X-Trace-Id: trace-123\r\n", $sent);
        $this->assertStringContainsString("User-Agent: commondoc\r\n", $sent);
    }

    /**
     * Every control character in a rejected URL is escaped or stripped, one at a time.
     *
     * The message must be safe to write to a log, so a CR, an LF, a tab and a NUL each get their
     * own treatment. A single case would leave the other three untested.
     */
    #[Test]
    public function describesEveryControlCharacterInARejectedUrl(): void
    {
        foreach (["\r" => '\r', "\n" => '\n', "\t" => '\t', "\0" => '\0'] as $raw => $shown) {
            $message = $this->rejectionOf("http://127.0.0.1:1/{$raw}", [])->getMessage();

            static::assertStringContainsString($shown, $message);
            static::assertStringNotContainsString($raw, $message);
        }
    }

    /**
     * PSR-18 forbids following a redirect, and the cURL option keeps that true rather than leaving
     * it to the contract alone.
     */
    #[Test]
    public function doesNotFollowARedirect(): void
    {
        $server = LoopbackHttpServer::start('redirect');

        try {
            $response = new CurlHttpTransport()->sendRequest(
                new RequestFactory()->createRequest('GET', $server->url('/moved')),
            );

            // The redirect is the answer, not something to chase: the second hop is never
            // attempted, so nothing serves `/elsewhere` and a client that followed would fail.
            static::assertSame(302, $response->getStatusCode());
            static::assertSame('/elsewhere', $response->getHeaderLine('location'));
        } finally {
            $server->stop();
        }
    }

    /**
     * The guard lives in the options; the transport must not send what the options refuse.
     *
     * Nothing listens on the URL, so a request that survived the guard would fail at connect time
     * instead, which is what the helper distinguishes.
     */
    #[Test]
    public function refusesToSendARequestWithAForgedHeader(): void
    {
        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            'Authorization' => "Bearer abc\nX-Injected: yes",
        ]);

        $this->assertStringContainsString('Authorization', $exception->getMessage());
    }

    /**
     * A transfer that fails names the URL and repeats cURL's own reason.
     *
     * Nothing listens on the port, so the failure arrives at connect time rather than waiting for
     * a timeout to expire. The reason is asserted by the absence of the fallback rather than by
     * its text: `curl_error()` words a refused connection differently per platform, but it is
     * never empty, so a message carrying the placeholder would be carrying the wrong one.
     */
    #[Test]
    public function reportsATransportFailureWithTheUrlAndTheReason(): void
    {
        try {
            new CurlHttpTransport()->sendRequest(
                new RequestFactory()->createRequest(
                    method: 'GET',
                    uri   : self::CLOSED_PORT,
                ),
            );

            $this->fail('A connection to a closed port must not produce a response.');
        } catch (HttpException $exception) {
            static::assertStringContainsString(self::CLOSED_PORT, $exception->getMessage());
            static::assertStringContainsString('failed', $exception->getMessage());
            static::assertStringNotContainsString('unknown cURL error', $exception->getMessage());
        }
    }

    /**
     * The success path, over a real HTTP server in a child process.
     *
     * The client blocks until a response arrives, so a single process cannot answer it. A child
     * process can, and it is not a mock: the transport opens a socket, sends a request, reads and
     * parses the response, which is what it does in production. See `LoopbackHttpServer` for why
     * the cURL functions cannot simply be stubbed.
     */
    #[Test]
    public function returnsWhatTheServerSent(): void
    {
        $server = LoopbackHttpServer::start('ok');

        try {
            $response = new CurlHttpTransport()->sendRequest(
                new RequestFactory()->createRequest('GET', $server->url('/health')),
            );

            static::assertSame(200, $response->getStatusCode());
            static::assertSame('hello-from-server', (string) $response->getBody());
            // A header proves the collect-and-build half, which RETURNTRANSFER and the header
            // callback both have to be set for. Header values that repeat are kept as a list.
            static::assertSame(['text/plain'], $response->getHeader('content-type'));
            static::assertSame(['a=1', 'b=2'], $response->getHeader('set-cookie'));
        } finally {
            $server->stop();
        }
    }

    /**
     * A request body reaches the server, which is the half a byte-capture cannot prove.
     */
    #[Test]
    public function sendsTheRequestBodyItWasGiven(): void
    {
        $server = LoopbackHttpServer::start('echo');

        try {
            $request = new RequestFactory()
                ->createRequest('POST', $server->url('/echo'))
                ->withBody(new StreamFactory()->createStream('payload-123'));

            $response = new CurlHttpTransport()->sendRequest($request);

            static::assertSame('echo:payload-123', (string) $response->getBody());
            static::assertSame('POST', $response->getHeaderLine('x-method'));
        } finally {
            $server->stop();
        }
    }

    /**
     * PSR-18 hands the client a whole request, so the method and the body reach the wire.
     */
    #[Test]
    public function theMethodAndBodyReachTheWire(): void
    {
        $sent = $this->sentBytes(
            new RequestFactory()
                ->createRequest(
                    method: 'POST',
                    uri   : 'https://example.test/',
                )
                ->withBody(new StreamFactory()->createStream('payload-123')),
        );

        static::assertStringStartsWith('POST / HTTP/1.1', $sent);
        static::assertStringContainsString('payload-123', $sent);
    }

    /**
     * A request built by a permissive message implementation, so the transport's own guard runs
     * instead of the PSR-7 layer rejecting the bytes first.
     *
     * @param array<string, string> $headers
     */
    private function rejectionOf(string $url, array $headers): HttpException
    {
        $request = new UnsafeRequest(
            new UnsafeUri($url),
            array_map(static fn(string $value): array => [$value], $headers),
        );

        try {
            new CurlHttpTransport(0.25)->sendRequest($request);
        } catch (HttpException $exception) {
            if (str_starts_with($exception->getMessage(), 'Refusing to send a request')) {
                return $exception;
            }

            $this->fail(sprintf('The transport attempted the request: %s', $exception->getMessage()));
        }

        $this->fail('The transport sent a request that should have been rejected.');
    }

    /**
     * A server in a child process answers with the bytes it was sent, which is what the transport
     * wrote. It answers, so this never depends on the client's timeout to end the request.
     */
    private function sentBytes(RequestInterface $request): string
    {
        $server = LoopbackHttpServer::start(HttpServerFixture::SCENARIO_RAW);

        try {
            $response = new CurlHttpTransport()->sendRequest(
                $request->withUri(new Uri($server->url('/'))),
            );
        } finally {
            $server->stop();
        }

        return (string) $response->getBody();
    }
}
