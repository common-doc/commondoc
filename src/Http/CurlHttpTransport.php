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
use CurlHandle;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function is_string;
use function preg_match;
use function preg_replace;
use function sprintf;
use function strlen;
use function strtr;
use function substr;

use const CURLINFO_RESPONSE_CODE;

/**
 * The production PSR-18 client: ext-curl, no redirects, a bounded timeout, and no credential in any
 * message it can raise.
 *
 * This class owns the transfer and nothing else. What a request becomes is `CurlOptions`, and what
 * a response becomes is `ResponseBuilder`, so both of those are assertable without a network and
 * this file is left with the part a test cannot supply: a cURL handle.
 *
 * Ported from the multi-repository component in `webinertia/asciidoc-php`, where it was written
 * against the same PSR-7 and PSR-18 contracts.
 *
 * @api
 */
final readonly class CurlHttpTransport implements ClientInterface
{
    public const string USER_AGENT = 'commondoc';

    /**
     * Control characters in a URL reach libcurl on the same untrusted path as a header.
     */
    private const string URL_CONTROL_PATTERN = '/[\x00-\x1F\x7F]/';

    private const int URL_DESCRIPTION_LIMIT = 64;

    public function __construct(
        private float $timeoutSeconds = 30.0,
        private CurlOptions $options = new CurlOptions(),
        private ResponseBuilder $builder = new ResponseBuilder(),
    ) {}

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();

        $this->assertUrlIsSendable($url);

        $handle = curl_init($url);

        // curl_init() is declared CurlHandle|false, so this guard is required for the type. It
        // does not return false on any PHP this package supports, which makes the branch
        // unreachable in practice.
        if (! $handle instanceof CurlHandle) {
            throw new HttpException(sprintf('Unable to initialise a cURL handle for %s.', $url));
        }

        $collector = new HeaderCollector();

        curl_setopt_array(
            $handle,
            $this->options->build($request, $collector, $this->timeoutSeconds),
        );

        $body = curl_exec($handle);

        if (! is_string($body)) {
            $error = curl_error($handle);

            throw new HttpException(sprintf(
                'HTTP request to %s failed: %s',
                $url,
                '' === $error ? 'unknown cURL error' : $error,
            ));
        }

        return $this->builder->build(
            status : (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            headers: $collector->headers(),
            body   : $body,
        );
    }

    /**
     * @throws HttpException when the URL carries a control character
     */
    private function assertUrlIsSendable(string $url): void
    {
        if (1 !== preg_match(self::URL_CONTROL_PATTERN, $url)) {
            return;
        }

        throw new HttpException(sprintf(
            'Refusing to send a request: the URL "%s" contains a control character.',
            $this->describe($url),
        ));
    }

    /**
     * Control characters become their escaped form so a rejected URL cannot inject a log line.
     */
    private function describe(string $text): string
    {
        $escaped = strtr($text, [
            "\0" => '\0',
            "\t" => '\t',
            "\n" => '\n',
            "\r" => '\r',
        ]);

        $escaped = (string) preg_replace(
            self::URL_CONTROL_PATTERN,
            replacement: '',
            subject: $escaped,
        );

        if (strlen($escaped) <= self::URL_DESCRIPTION_LIMIT) {
            return $escaped;
        }

        return substr($escaped, offset: 0, length: self::URL_DESCRIPTION_LIMIT) . '...';
    }
}
