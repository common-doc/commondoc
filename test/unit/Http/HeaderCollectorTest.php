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

use CommonDoc\Http\HeaderCollector;
use CurlHandle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function curl_init;
use function strlen;

/**
 * The cURL header callback, driven line by line.
 *
 * It exists because `CURLOPT_HEADERFUNCTION` needs a callable, and it is the only place a received
 * header line is parsed. A header that repeats has to keep both values, because PSR-7 allows it, and
 * a redirect chain has to leave the final response's headers rather than the first.
 */
#[CoversClass(HeaderCollector::class)]
#[CoversMethod(HeaderCollector::class, '__invoke')]
#[CoversMethod(HeaderCollector::class, 'headers')]
final class HeaderCollectorTest extends TestCase
{
    #[Test]
    public function ignoresABlankLine(): void
    {
        $collector = new HeaderCollector();
        $line      = "\r\n";

        $collector($this->handle(), line: "X-Real: yes\r\n");
        $returned = $collector($this->handle(), line: $line);

        static::assertSame(strlen($line), $returned);
        static::assertSame(['x-real' => ['yes']], $collector->headers());
    }

    #[Test]
    public function ignoresALineWithAnEmptyName(): void
    {
        $collector = new HeaderCollector();

        $collector($this->handle(), line: ": nameless\r\n");

        // A name of nothing would produce an entry PSR-7 then refuses to add, which would turn a
        // server-controlled header into an exception.
        static::assertSame([], $collector->headers());
    }

    #[Test]
    public function ignoresALineWithNoColon(): void
    {
        $collector = new HeaderCollector();

        $collector($this->handle(), line: "this is not a header\r\n");

        static::assertSame([], $collector->headers());
    }

    #[Test]
    public function keepsAHeaderLowercasedAndTrimmed(): void
    {
        $collector = new HeaderCollector();

        $collector($this->handle(), line: "  X-Trace-Id :   trace-123   \r\n");

        // Lowercase and trimmed to match how PSR-7 stores a header, so the response can be built
        // from this map without a second normalisation.
        static::assertSame(['x-trace-id' => ['trace-123']], $collector->headers());
    }

    #[Test]
    public function keepsAnEmptyValue(): void
    {
        $collector = new HeaderCollector();

        $collector($this->handle(), line: "X-Empty:\r\n");

        static::assertSame(['x-empty' => ['']], $collector->headers());
    }

    #[Test]
    public function keepsAValueThatIsASingleCharacter(): void
    {
        $collector = new HeaderCollector();

        $collector($this->handle(), line: "X-A:b\r\n");

        // No space after the colon, and a one-character value: an offset that skipped the colon
        // plus anything more than one character would lose it.
        static::assertSame(['x-a' => ['b']], $collector->headers());
    }

    #[Test]
    public function keepsBothValuesWhenAHeaderRepeats(): void
    {
        $collector = new HeaderCollector();

        $collector($this->handle(), line: "Set-Cookie: a=1\r\n");
        $collector($this->handle(), line: "Set-Cookie: b=2\r\n");

        static::assertSame(['set-cookie' => ['a=1', 'b=2']], $collector->headers());
    }

    /**
     * A status line is never offered to the header parser, whatever it contains.
     *
     * RFC 7230 defines a reason phrase as `VCHAR`, which includes a colon, so a status line can
     * look like a header. The branches are exclusive for exactly that reason.
     */
    #[Test]
    public function neverParsesAStatusLineAsAHeaderEvenWhenItsReasonPhraseHasAColon(): void
    {
        $collector = new HeaderCollector();

        $collector($this->handle(), line: "Location: https://example.test/next\r\n");
        $collector($this->handle(), line: "HTTP/1.1 200 Look: here\r\n");

        static::assertSame([], $collector->headers());
    }

    #[Test]
    public function recognisesAStatusLineThatCarriesSurroundingWhitespace(): void
    {
        $collector = new HeaderCollector();

        $collector($this->handle(), line: "Location: https://example.test/next\r\n");
        $collector($this->handle(), line: "  HTTP/1.1 200 OK  \r\n");

        // The line is trimmed before it is compared, so a padded status line still resets the map.
        // Without the trim the header from the hop that was followed would survive into the final
        // response.
        static::assertSame([], $collector->headers());
    }

    #[Test]
    public function resetsOnANewStatusLineSoARedirectLeavesOnlyTheFinalResponse(): void
    {
        $collector = new HeaderCollector();

        $collector($this->handle(), line: "HTTP/1.1 301 Moved Permanently\r\n");
        $collector($this->handle(), line: "Location: https://example.test/next\r\n");

        static::assertSame(['location' => ['https://example.test/next']], $collector->headers());

        $collector($this->handle(), line: "HTTP/1.1 200 OK\r\n");

        // The caller is building the final response, so a header from the hop that was followed
        // must not survive into it.
        static::assertSame([], $collector->headers());
    }

    #[Test]
    public function returnsTheLineLengthForAStatusLineToo(): void
    {
        $collector = new HeaderCollector();
        $line      = "HTTP/1.1 200 OK\r\n";

        // cURL treats a short return as a transfer error, so every branch that answers has to
        // return the length, not only the blank-line branch.
        static::assertSame(strlen($line), $collector($this->handle(), line: $line));
    }

    #[Test]
    public function returnsTheLineLengthSoCurlKeepsReading(): void
    {
        $line = "Content-Type: text/html\r\n";

        // cURL treats a short return as a transfer error, so the length is part of the contract.
        static::assertSame(strlen($line), new HeaderCollector()($this->handle(), $line));
    }

    #[Test]
    public function startsWithNoHeaders(): void
    {
        static::assertSame([], new HeaderCollector()->headers());
    }

    private function handle(): CurlHandle
    {
        $handle = curl_init();

        static::assertInstanceOf(CurlHandle::class, $handle);

        return $handle;
    }
}
