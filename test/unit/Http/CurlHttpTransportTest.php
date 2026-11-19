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
use CommonDocTest\Support\LoopbackHttpServer;
use CommonDocTest\Support\UnsafeRequest;
use CommonDocTest\Support\UnsafeUri;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function array_map;
use function fclose;
use function sprintf;
use function str_repeat;
use function str_starts_with;
use function stream_get_contents;
use function stream_set_timeout;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;
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
    public function aBareLineFeedInAHeaderValueIsRejected(): void
    {
        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            'Authorization' => "Bearer abc\nX-Injected: yes",
        ]);

        $this->assertStringContainsString('Authorization', $exception->getMessage());
    }

    #[Test]
    public function aCrlfInAHeaderNameIsRejected(): void
    {
        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            "X-Test\r\nX-Injected" => 'yes',
        ]);

        $this->assertStringContainsString('X-Test\\r\\nX-Injected', $exception->getMessage());
    }

    /**
     * A header value carrying a CRLF arrives as two headers. libcurl sends it without complaint.
     */
    #[Test]
    public function aCrlfInAHeaderValueIsRejected(): void
    {
        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            'Authorization' => "Bearer abc\r\nX-Injected: yes",
        ]);

        $this->assertStringContainsString('Authorization', $exception->getMessage());
    }

    #[Test]
    public function aDescriptionOfExactlyTheLimitIsNotTruncated(): void
    {
        $visible = str_repeat('a', times: self::DESCRIPTION_LIMIT - strlen(self::CLOSED_PORT) - 2);

        $message = $this->rejectionOf(self::CLOSED_PORT . "{$visible}\r", [])->getMessage();

        $this->assertStringContainsString(self::CLOSED_PORT . "{$visible}\\r", $message);
        $this->assertStringNotContainsString('...', $message);
    }

    #[Test]
    public function aHeaderNameContainingAColonIsRejected(): void
    {
        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            'X-Test: X-Injected' => 'yes',
        ]);

        $this->assertStringContainsString('X-Test', $exception->getMessage());
    }

    #[Test]
    public function aHeaderNameContainingASpaceIsRejected(): void
    {
        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            'X Test' => 'yes',
        ]);

        $this->assertStringContainsString('X Test', $exception->getMessage());
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
    public function anEmptyHeaderNameIsRejected(): void
    {
        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            '' => 'yes',
        ]);

        $this->assertStringContainsString('not a valid HTTP header name', $exception->getMessage());
    }

    /**
     * diactoros permits an obs-fold and unfolds it into one value; the transport rejects every CR
     * and LF instead. The divergence is deliberate, so both halves of it are pinned here.
     */
    #[Test]
    public function anObsFoldInAHeaderValueIsRejected(): void
    {
        $unfolded = new Request()
            ->withHeader('X-Folded', value: "abc\r\n folded")
            ->getHeaderLine('X-Folded');

        $this->assertSame('abc folded', $unfolded, message: 'The PSR-7 implementation unfolds an obs-fold.');

        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            'X-Folded' => "abc\r\n folded",
        ]);

        $this->assertStringContainsString('X-Folded', $exception->getMessage());
        $this->assertStringNotContainsString('abc', $exception->getMessage());
    }

    #[Test]
    public function aNulInAHeaderNameIsRejected(): void
    {
        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            "X\0Test" => 'yes',
        ]);

        $this->assertStringContainsString('X\\0Test', $exception->getMessage());
    }

    #[Test]
    public function aNulInAHeaderValueIsRejected(): void
    {
        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            'Accept' => "application/json\0",
        ]);

        $this->assertStringContainsString('Accept', $exception->getMessage());
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
     */
    #[Test]
    public function cleanHeadersReachTheWireUnchanged(): void
    {
        $request = new RequestFactory()
            ->createRequest('GET', self::CLOSED_PORT)
            ->withHeader('Accept', value: 'application/vnd.github+json')
            ->withHeader('X-Trace-Id', value: 'trace-123');

        $captured = $this->capturedRequest($request);

        $this->assertStringContainsString("Accept: application/vnd.github+json\r\n", $captured);
        $this->assertStringContainsString("X-Trace-Id: trace-123\r\n", $captured);
        $this->assertStringContainsString("User-Agent: commondoc\r\n", $captured);
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
     *
     * The listener never answers, so this ends in a transport failure: what it proves is the
     * bytes the transport wrote, not the response.
     */
    #[Test]
    public function theMethodAndBodyReachTheWire(): void
    {
        $request = new RequestFactory()
            ->createRequest('POST', self::CLOSED_PORT)
            ->withBody(new StreamFactory()->createStream('payload-123'));

        $captured = $this->capturedRequest($request);

        static::assertStringStartsWith('POST / HTTP/1.1', $captured);
        static::assertStringContainsString('payload-123', $captured);
    }

    public function theRejectionNamesTheHeaderAndNeverItsValue(): void
    {
        $sentinel = 'do-not-log-me';

        $exception = $this->rejectionOf(self::CLOSED_PORT, [
            'Authorization' => sprintf('Bearer %s%s', $sentinel, "\r\nX-Injected: yes"),
        ]);

        $message = $exception->getMessage();

        $this->assertStringContainsString('Authorization', $message);
        $this->assertStringNotContainsString($sentinel, $message);
        $this->assertStringNotContainsString('Bearer', $message);
        $this->assertStringNotContainsString('X-Injected', $message);
    }

    /**
     * A loopback listener captures the bytes the transport writes. It never answers, so the request
     * must end in a transport failure rather than a response.
     */
    private function capturedRequest(RequestInterface $request): string
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        if (false === $server) {
            $this->fail(sprintf('Unable to listen on a loopback port: %s (%d).', $error, $errno));
        }

        $address = (string) stream_socket_get_name($server, remote: false);
        $failure = null;

        try {
            new CurlHttpTransport(0.25)->sendRequest(
                $request->withUri(new Uri(sprintf('http://%s/', $address))),
            );
        } catch (HttpException $exception) {
            $failure = $exception;
        }

        $connection = stream_socket_accept($server, timeout: 5);

        if (false === $connection) {
            $this->fail('The transport never opened a connection to the listener.');
        }

        stream_set_timeout($connection, seconds: 5);
        $captured = (string) stream_get_contents($connection);
        fclose($connection);
        fclose($server);

        $this->assertInstanceOf(
            HttpException::class,
            $failure,
            message: 'The listener never answers, so the request must end in a transport failure.',
        );

        return $captured;
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
}
