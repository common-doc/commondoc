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

use RuntimeException;

use function escapeshellarg;
use function fclose;
use function fgets;
use function getenv;
use function is_resource;
use function proc_close;
use function proc_open;
use function sprintf;
use function str_starts_with;
use function stream_get_contents;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;
use const PHP_BINARY;

/**
 * A real HTTP server in a child process, for the tests that need one to answer.
 *
 * The transport blocks until a response arrives, so a single process cannot both wait on the
 * client and answer it. That is the reason this exists rather than a mock: the global `curl_*`
 * functions are imported explicitly in the transport, which binds them at compile time, so a
 * namespaced override cannot intercept them, and a production seam purely for a test is the kind
 * of speculative abstraction this package avoids.
 *
 * A child process is not a mock. The client under test opens a socket, writes a request, reads a
 * response and parses it, which is what it does in production.
 *
 * The port is chosen by the operating system and read back from the child, so nothing here depends
 * on a port being free on whatever machine runs the suite.
 */
final class LoopbackHttpServer
{
    /**
     * @param resource             $process
     * @param array<int, resource> $pipes
     */
    private function __construct(
        private mixed $process,
        private array $pipes,
        private readonly int $port,
    ) {}

    /**
     * Start a server that answers once, with the response its scenario names.
     *
     * @param 'ok'|'redirect'|'echo' $scenario
     *
     * @throws RuntimeException When the server cannot be started or never reports its port
     */
    public static function start(string $scenario): self
    {
        $command = sprintf(
            '%s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'http-server.php'),
            escapeshellarg($scenario),
        );

        // The child is given an explicit environment with coverage turned off. It produces no
        // coverage data, so inheriting `XDEBUG_MODE=coverage` would only instrument a process
        // nobody measures, and the coverage job is where that cost would land.
        $environment = [...getenv(), 'XDEBUG_MODE' => 'off'];

        $process = proc_open(
            command        : $command,
            descriptor_spec: [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            pipes          : $pipes,
            cwd            : null,
            env_vars       : $environment,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('The loopback server could not be started.');
        }

        $announced = fgets($pipes[1]);

        if (false === $announced || ! str_starts_with(trim($announced), 'PORT ')) {
            // Drained so a failure in the child cannot block on a full pipe.
            stream_get_contents($pipes[2]);
            proc_close($process);

            throw new RuntimeException('The loopback server did not report a port.');
        }

        return new self(
            $process,
            $pipes,
            (int) substr(
                string: trim($announced),
                offset: 5,
            ),
        );
    }

    /**
     * Wait for the child to finish, then release it.
     *
     * Idempotent, because the caller may stop explicitly and the destructor runs afterwards.
     */
    public function stop(): void
    {
        if (! is_resource($this->process)) {
            return;
        }

        // Both pipes are drained before the wait: a child blocked writing to a full pipe would
        // never exit, and the wait would hang the suite rather than fail it.
        foreach ($this->pipes as $pipe) {
            if (! is_resource($pipe)) {
                continue;
            }

            stream_get_contents($pipe);
            fclose($pipe);
        }
        proc_close($this->process);

        $this->process = null;
        $this->pipes   = [];
    }

    /**
     * The URL to send a request to.
     */
    public function url(string $path = '/'): string
    {
        return sprintf('http://127.0.0.1:%d%s', $this->port, $path);
    }

    public function __destruct()
    {
        $this->stop();
    }
}
