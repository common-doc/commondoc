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

use CommonDoc\Http\CurlOptions;
use CommonDoc\Http\HeaderCollector;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

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
 */
#[CoversClass(CurlOptions::class)]
#[CoversMethod(CurlOptions::class, 'build')]
final class CurlOptionsTest extends TestCase
{
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
}
