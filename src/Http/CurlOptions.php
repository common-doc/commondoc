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

namespace CommonDoc\Http;

use CommonDoc\Exception\HttpException;
use Psr\Http\Message\RequestInterface;

use function preg_match;
use function preg_replace;
use function round;
use function sprintf;
use function strlen;
use function strtr;
use function substr;

use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT_MS;

/**
 * The cURL options a request becomes, built without touching cURL.
 *
 * Separated from the transport so that "what does a request map to" is assertable without a
 * transfer. That matters for the timeout options in particular: an option a mutant removes cannot
 * be observed by a request that simply succeeds, and the same mutant makes a request hang, which
 * Infection reports as a time out rather than as a killed mutant. A time out conceals the result
 * instead of proving it, so those two options are pinned here as values.
 *
 * The header checks stay local because the guarantee cannot rest on the message implementation. A
 * PSR-7 implementation must validate, but nothing requires it to be diactorios, and diactoros is no
 * substitute anyway: it rewrites a control character in a URI instead of rejecting it, and it
 * permits an obs-fold, which it unfolds into one value.
 *
 * @internal
 */
final class CurlOptions
{
    /**
     * RFC 7230 `tchar`, the only characters a header field name may contain.
     */
    private const string HEADER_NAME_PATTERN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/';

    /**
     * A CR, an LF, or a NUL ends the header line, so any of them lets a value forge a second one.
     *
     * Deliberately stricter than the PSR-7 implementation: diactoros permits an obs-fold and
     * unfolds it into a single value, while this pattern rejects every CR and LF. That divergence
     * is intended, so do not relax it to match.
     */
    private const string HEADER_VALUE_PATTERN = '/[\r\n\x00]/';

    /**
     * Every control character, which is what makes a rejected value safe to write to a log.
     */
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';

    private const int URL_DESCRIPTION_LIMIT = 64;

    /**
     * Control characters become their escaped form so a rejected name cannot inject a log line.
     */
    private static function describe(string $text): string
    {
        $escaped = strtr($text, [
            "\0" => '\0',
            "\t" => '\t',
            "\n" => '\n',
            "\r" => '\r',
        ]);

        // The cast narrows the analyzer's `string|null` rather than converting anything:
        // preg_replace returns null only on an error, which this fixed valid pattern cannot
        // produce. Removing it therefore cannot change one character of the result, so the
        // mutant that removes it is a genuine equivalent.
        // @infection-ignore CastString
        $escaped = (string) preg_replace(
            self::CONTROL_CHARACTER_PATTERN,
            replacement: '',
            subject: $escaped,
        );

        if (strlen($escaped) <= self::URL_DESCRIPTION_LIMIT) {
            return $escaped;
        }

        return substr($escaped, offset: 0, length: self::URL_DESCRIPTION_LIMIT) . '...';
    }

    private static function milliseconds(float $timeoutSeconds): int
    {
        return (int) round($timeoutSeconds * 1000.0);
    }

    /**
     * @throws HttpException when a header would forge a line, or carries a CR or an LF
     *
     * @return array<int, mixed>
     */
    public function build(
        RequestInterface $request,
        HeaderCollector $collector,
        float $timeoutSeconds,
    ): array {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            // PSR-18 already forbids following a redirect. The explicit option keeps that true
            // rather than leaving it to the contract alone.
            CURLOPT_FOLLOWLOCATION    => false,
            CURLOPT_CONNECTTIMEOUT_MS => self::milliseconds($timeoutSeconds),
            CURLOPT_TIMEOUT_MS        => self::milliseconds($timeoutSeconds),
            CURLOPT_HTTPHEADER        => $this->requestHeaders($request),
            CURLOPT_HEADERFUNCTION    => $collector,
            CURLOPT_CUSTOMREQUEST     => $request->getMethod(),
        ];

        $body = (string) $request->getBody();

        // Only when there is one: an empty POSTFIELDS turns a GET into a POST with a body.
        if ('' !== $body) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        return $options;
    }

    /**
     * The message names the header and never its value, because the value may be the credential.
     *
     * @throws HttpException when the header would forge a line, or carries a CR or an LF
     */
    private function assertHeaderIsSendable(string $name, string $value): void
    {
        if (1 !== preg_match(self::HEADER_NAME_PATTERN, $name)) {
            throw new HttpException(sprintf(
                'Refusing to send a request: "%s" is not a valid HTTP header name.',
                self::describe($name),
            ));
        }

        if (1 === preg_match(self::HEADER_VALUE_PATTERN, $value)) {
            throw new HttpException(sprintf(
                'Refusing to send a request: the value of the "%s" header contains a line break.',
                $name,
            ));
        }
    }

    /**
     * Rejects anything that would forge a header line. libcurl does not reject a CR or an LF in a
     * header value; it sends it, so the second line arrives as a header of its own.
     *
     * @return list<string>
     *
     * @throws HttpException when a header would forge a line, or carries a CR or an LF
     */
    private function requestHeaders(RequestInterface $request): array
    {
        $requestHeaders = [sprintf('User-Agent: %s', CurlHttpTransport::USER_AGENT)];

        /** @var array<string, list<string>> $headers */
        $headers = $request->getHeaders();

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $this->assertHeaderIsSendable($name, $value);

                $requestHeaders[] = sprintf('%s: %s', $name, $value);
            }
        }

        return $requestHeaders;
    }
}
