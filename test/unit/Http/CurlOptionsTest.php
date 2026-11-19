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
use CommonDoc\Http\CurlOptions;
use CommonDoc\Http\HeaderCollector;
use CommonDocTest\Support\UnsafeRequest;
use CommonDocTest\Support\UnsafeUri;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\StreamFactory;
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

use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT_MS;

/**
 * What a request becomes, asserted as values rather than observed through a transfer.
 *
 * This exists because two of these options cannot be observed by a request that succeeds: the
 * timeouts. A mutant that removes one makes the request hang rather than fail, so Infection
 * reports a time out, and a time out conceals the mutant instead of killing it. Pinning the
 * options by value is the only way to settle them.
 *
 * The rejections belong here too: the guard is this class's, and the transport only propagates
 * what it raises.
 */
#[CoversClass(CurlOptions::class)]
#[CoversMethod(CurlOptions::class, 'build')]
final class CurlOptionsTest extends TestCase
{
    #[Test]
    public function aBareLineFeedInAHeaderValueIsRejected(): void
    {
        $exception = $this->rejectionOf([
            'Authorization' => "Bearer abc\nX-Injected: yes",
        ]);

        static::assertStringContainsString('Authorization', $exception->getMessage());
    }

    #[Test]
    public function aCrlfInAHeaderNameIsRejected(): void
    {
        $exception = $this->rejectionOf([
            "X-Test\r\nX-Injected" => 'yes',
        ]);

        static::assertStringContainsString('X-Test\\r\\nX-Injected', $exception->getMessage());
    }

    /**
     * A header value carrying a CRLF arrives as two headers. libcurl sends it without complaint.
     */
    #[Test]
    public function aCrlfInAHeaderValueIsRejected(): void
    {
        $exception = $this->rejectionOf([
            'Authorization' => "Bearer abc\r\nX-Injected: yes",
        ]);

        static::assertStringContainsString('Authorization', $exception->getMessage());
    }

    #[Test]
    public function aHeaderNameContainingAColonIsRejected(): void
    {
        $exception = $this->rejectionOf([
            'X-Test: X-Injected' => 'yes',
        ]);

        static::assertStringContainsString('X-Test', $exception->getMessage());
    }

    #[Test]
    public function aHeaderNameContainingASpaceIsRejected(): void
    {
        $exception = $this->rejectionOf([
            'X Test' => 'yes',
        ]);

        static::assertStringContainsString('X Test', $exception->getMessage());
    }

    /**
     * A rejected name is truncated like any other, so a hostile header cannot flood a log line.
     *
     * The first character is unique, so an off-by-one in the offset shifts it out of the message.
     */
    #[Test]
    public function aNameLongerThanTheLimitIsTruncatedInTheRejection(): void
    {
        $padding = 'X' . str_repeat('a', times: 80);

        $exception = $this->rejectionOf([
            "{$padding} " => 'yes',
        ]);

        static::assertStringContainsString('X' . str_repeat('a', times: 63) . '...', $exception->getMessage());
        static::assertStringNotContainsString($padding, $exception->getMessage());
    }

    /**
     * A name of exactly the limit is left whole, so the boundary is pinned from both sides.
     */
    #[Test]
    public function aNameOfExactlyTheLimitIsNotTruncatedInTheRejection(): void
    {
        $name = 'X' . str_repeat('a', times: 62) . ' ';

        static::assertSame(64, strlen($name));

        $message = $this->rejectionOf([$name => 'yes'])->getMessage();

        static::assertStringContainsString($name, $message);
        static::assertStringNotContainsString('...', $message);
    }

    #[Test]
    public function anEmptyHeaderNameIsRejected(): void
    {
        $exception = $this->rejectionOf([
            '' => 'yes',
        ]);

        static::assertStringContainsString('not a valid HTTP header name', $exception->getMessage());
    }

    /**
     * diactoros permits an obs-fold and unfolds it into one value; this guard rejects every CR and
     * LF instead. The divergence is deliberate, so both halves of it are pinned here.
     */
    #[Test]
    public function anObsFoldInAHeaderValueIsRejected(): void
    {
        $unfolded = new Request()
            ->withHeader('X-Folded', value: "abc\r\n folded")
            ->getHeaderLine('X-Folded');

        static::assertSame('abc folded', $unfolded, message: 'The PSR-7 implementation unfolds an obs-fold.');

        $exception = $this->rejectionOf([
            'X-Folded' => "abc\r\n folded",
        ]);

        static::assertStringContainsString('X-Folded', $exception->getMessage());
        static::assertStringNotContainsString('abc', $exception->getMessage());
    }

    #[Test]
    public function aNulInAHeaderNameIsRejected(): void
    {
        $exception = $this->rejectionOf([
            "X\0Test" => 'yes',
        ]);

        static::assertStringContainsString('X\\0Test', $exception->getMessage());
    }

    #[Test]
    public function aNulInAHeaderValueIsRejected(): void
    {
        $exception = $this->rejectionOf([
            'Accept' => "application/json\0",
        ]);

        static::assertStringContainsString('Accept', $exception->getMessage());
    }

    #[Test]
    public function capturesTheBodyAndDoesNotFollowRedirects(): void
    {
        $options = $this->build($this->get('https://example.test/'));

        // Both are contract requirements rather than preferences: without RETURNTRANSFER cURL
        // writes the body to stdout, and PSR-18 forbids following a redirect.
        static::assertTrue($options[CURLOPT_RETURNTRANSFER]);
        static::assertFalse($options[CURLOPT_FOLLOWLOCATION]);
    }

    #[Test]
    public function convertsTheTimeoutToBothMillisecondOptions(): void
    {
        $options = $this->build($this->get('https://example.test/'), timeoutSeconds: 1.5);

        // Both are asserted separately, and each is asserted as a value: an option a mutant
        // removes or swaps is invisible to a request that simply succeeds.
        static::assertSame(1500, $options[CURLOPT_CONNECTTIMEOUT_MS]);
        static::assertSame(1500, $options[CURLOPT_TIMEOUT_MS]);
    }

    #[Test]
    public function handsCurlTheCollectorAsTheHeaderCallback(): void
    {
        $collector = new HeaderCollector();
        $options   = new CurlOptions()->build(
            $this->get('https://example.test/'),
            $collector,
            timeoutSeconds: 30.0,
        );

        static::assertSame($collector, $options[CURLOPT_HEADERFUNCTION]);
    }

    #[Test]
    public function omitsTheBodyOptionWhenThereIsNoBody(): void
    {
        $options = $this->build($this->get('https://example.test/'));

        // An empty POSTFIELDS turns a GET into a POST with a body, so its absence is the point.
        static::assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
    }

    #[Test]
    public function roundsHalfUpRatherThanUpAlways(): void
    {
        // 1.0004s is 1000.4ms: rounding keeps 1000, while a ceiling would give 1001. Together with
        // the sub-millisecond case this pins rounding against both neighbours.
        $options = $this->build($this->get('https://example.test/'), timeoutSeconds: 1.0004);

        static::assertSame(1000, $options[CURLOPT_TIMEOUT_MS]);
    }

    #[Test]
    public function roundsTheTimeoutRatherThanTruncatingIt(): void
    {
        // 0.0006s is 0.6ms, so rounding gives 1 and truncation gives 0. Zero means no timeout at
        // all to libcurl, which is the opposite of what was asked for.
        $options = $this->build($this->get('https://example.test/'), timeoutSeconds: 0.0006);

        static::assertSame(1, $options[CURLOPT_TIMEOUT_MS]);
    }

    #[Test]
    public function sendsBothValuesOfARepeatedHeader(): void
    {
        $options = $this->build(
            new Request(uri: 'https://example.test/')->withAddedHeader('Accept', ['text/html', 'text/plain']),
        );

        /** @var list<string> $headers */
        $headers = $options[CURLOPT_HTTPHEADER];

        static::assertContains('Accept: text/html', $headers);
        static::assertContains('Accept: text/plain', $headers);
    }

    #[Test]
    public function sendsTheBodyWhenThereIsOne(): void
    {
        $request = new RequestFactory()
            ->createRequest(
                method: 'POST',
                uri   : 'https://example.test/',
            )
            ->withBody(new StreamFactory()->createStream(content: 'payload-123'));

        static::assertSame('payload-123', $this->build($request)[CURLOPT_POSTFIELDS]);
    }

    #[Test]
    public function sendsTheMethod(): void
    {
        $options = $this->build(new Request(
            uri   : 'https://example.test/',
            method: 'DELETE',
        ));

        static::assertSame('DELETE', $options[CURLOPT_CUSTOMREQUEST]);
    }

    #[Test]
    public function sendsTheUserAgentAndEveryHeader(): void
    {
        $options = $this->build(
            new Request(uri: 'https://example.test/')
                ->withHeader('Accept', value: 'application/vnd.github+json')
                ->withHeader('X-Trace-Id', value: 'trace-123'),
        );

        /** @var list<string> $headers */
        $headers = $options[CURLOPT_HTTPHEADER];

        static::assertContains('User-Agent: commondoc', $headers);
        static::assertContains('Accept: application/vnd.github+json', $headers);
        static::assertContains('X-Trace-Id: trace-123', $headers);
    }

    /**
     * The message names the header and never its value, because the value may be the credential.
     */
    #[Test]
    public function theRejectionNamesTheHeaderAndNeverItsValue(): void
    {
        $sentinel = 'do-not-log-me';

        $exception = $this->rejectionOf([
            'Authorization' => sprintf('Bearer %s%s', $sentinel, "\r\nX-Injected: yes"),
        ]);

        $message = $exception->getMessage();

        static::assertStringContainsString('Authorization', $message);
        static::assertStringNotContainsString($sentinel, $message);
        static::assertStringNotContainsString('Bearer', $message);
        static::assertStringNotContainsString('X-Injected', $message);
    }

    /**
     * @return array<int, mixed>
     */
    private function build(?RequestInterface $request = null, float $timeoutSeconds = 30.0): array
    {
        return new CurlOptions()->build(
            $request ?? $this->get('https://example.test/'),
            new HeaderCollector(),
            $timeoutSeconds,
        );
    }

    private function get(string $url): RequestInterface
    {
        return new RequestFactory()->createRequest('GET', $url);
    }

    /**
     * A request built by a permissive message implementation, so this class's own guard runs
     * instead of the PSR-7 layer rejecting the bytes first.
     *
     * @param array<string, string> $headers
     */
    private function rejectionOf(array $headers): HttpException
    {
        $request = new UnsafeRequest(
            new UnsafeUri('https://example.test/'),
            array_map(static fn(string $value): array => [$value], $headers),
        );

        try {
            new CurlOptions()->build($request, new HeaderCollector(), timeoutSeconds: 30.0);
        } catch (HttpException $exception) {
            if (str_starts_with($exception->getMessage(), 'Refusing to send a request')) {
                return $exception;
            }

            $this->fail(sprintf('The options refused for another reason: %s', $exception->getMessage()));
        }

        $this->fail('The options accepted a header that should have been rejected.');
    }
}
