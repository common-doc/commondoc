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

namespace CommonDocTest\Support;

use function explode;
use function fclose;
use function fflush;
use function fgets;
use function fread;
use function fwrite;
use function sprintf;
use function stream_set_timeout;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;
use function stripos;
use function strlen;
use function strrpos;
use function substr;
use function trim;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * A one-request HTTP server, run as a separate process by `LoopbackHttpServer`.
 *
 * It exists because the transport blocks until a response arrives, so a single process cannot both
 * wait on the client and answer it. A child process can.
 *
 * The port is bound as 0 so the operating system picks a free one, and the chosen port is written
 * to stdout before accepting. A fixed port would collide with whatever else the machine runs and
 * turn the suite red for reasons unrelated to the code.
 *
 * The scenario selects the response, which is how one fixture serves the success, the redirect and
 * the request-body cases without a second process type.
 *
 * @internal
 */
final class HttpServerFixture
{
    public const string SCENARIO_OK       = 'ok';
    public const string SCENARIO_REDIRECT = 'redirect';
    public const string SCENARIO_ECHO     = 'echo';

    /**
     * Answers with the bytes it was sent, so a test can assert what reached the wire without the
     * client's own timeout being what ends the request.
     */
    public const string SCENARIO_RAW = 'raw';

    private const string PAYLOAD = 'hello-from-server';

    /**
     * @param list<string> $arguments
     */
    public static function run(array $arguments): int
    {
        $scenario = $arguments[1] ?? self::SCENARIO_OK;

        $server = stream_socket_server(
            address      : 'tcp://127.0.0.1:0',
            error_code   : $errorCode,
            error_message: $errorMessage,
        );

        if (false === $server) {
            fwrite(STDERR, sprintf('bind failed: %s (%d)%s', (string) $errorMessage, $errorCode, PHP_EOL));

            return 1;
        }

        $address = (string) stream_socket_get_name($server, remote: false);

        fwrite(
            STDOUT,
            'PORT '
                . substr(
                    $address,
                    (int) strrpos(
                        haystack: $address,
                        needle  : ':',
                    ) + 1,
                )
                . "\n",
        );
        fflush(STDOUT);

        $connection = stream_socket_accept($server, timeout: 10.0);

        if (false === $connection) {
            fclose($server);

            return 1;
        }

        stream_set_timeout($connection, seconds: 5);

        [$head, $body] = self::read($connection);

        self::respond($connection, $scenario, $head, $body);

        fclose($connection);
        fclose($server);

        return 0;
    }

    /**
     * Read the whole request, so a body the client sent has actually arrived before answering.
     *
     * @param resource $connection
     *
     * @return array{string, string} The request head, then its body
     */
    private static function read($connection): array
    {
        $head = '';

        while (false !== ($line = fgets($connection))) {
            $head .= $line;

            if ("\r\n" === $line || "\n" === $line) {
                break;
            }
        }

        $length = 0;

        foreach (explode("\r\n", $head) as $headerLine) {
            if (
                0 !== stripos(
                    haystack: $headerLine,
                    needle  : 'Content-Length:',
                )
            ) {
                continue;
            }

            $length = (int) trim(substr($headerLine, strlen('Content-Length:')));
        }

        return [$head, $length > 0 ? (string) fread($connection, $length) : ''];
    }

    /**
     * @param resource $connection
     */
    private static function respond($connection, string $scenario, string $head, string $body): void
    {
        if (self::SCENARIO_REDIRECT === $scenario) {
            fwrite(
                stream: $connection,
                data  : "HTTP/1.1 302 Found\r\nLocation: /elsewhere\r\nContent-Length: 0\r\nConnection: close\r\n\r\n",
            );

            return;
        }

        // The method is the first token of the request line, which is the head's first line.
        $parts = explode(
            separator: ' ',
            string   : $head,
        );
        $method = $parts[0] ?? '';

        [$payload, $extra] = match ($scenario) {
            self::SCENARIO_ECHO => ["echo:{$body}", "X-Method: {$method}\r\n"],
            self::SCENARIO_RAW  => [$head . $body, "X-Method: {$method}\r\n"],
            default             => [self::PAYLOAD, "Set-Cookie: a=1\r\nSet-Cookie: b=2\r\n"],
        };

        fwrite(
            stream: $connection,
            data  : sprintf(
                "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n%sContent-Length: %d\r\nConnection: close\r\n\r\n%s",
                $extra,
                strlen($payload),
                $payload,
            ),
        );
    }
}
