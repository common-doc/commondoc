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

namespace CommonDoc\Input;

use Psl\Async;
use Psl\Shell;

use function explode;
use function str_contains;
use function trim;

/**
 * Asks git a question of one checkout.
 *
 * The one place a git command is run, so a question about a checkout is asked the same
 * way whoever asks it: the repository identity of a source root, and the versions a
 * repository has released.
 *
 * @internal
 */
final readonly class Git
{
    /**
     * One git question, or null when it cannot be answered.
     *
     * `Shell\execute` runs the command with its arguments as separate values, so no
     * shell ever parses them and a path containing a space or a quote cannot become
     * part of a command. A non-zero exit is the ordinary "not a repository" answer
     * to most callers, which is why it is caught rather than allowed to fail the build.
     *
     * An empty working directory is refused rather than handed to the shell, because a
     * shell started in one would answer for wherever the process happens to be running
     * instead of for the checkout that was asked about.
     *
     * @param string       $directory The checkout to ask
     * @param list<string> $arguments The git arguments, one value each
     */
    public function ask(string $directory, array $arguments): ?string
    {
        $output = $this->run($directory, $arguments);

        if (null === $output) {
            return null;
        }

        $output = trim($output);

        return '' === $output ? null : $output;
    }

    /**
     * The paths of every file a ref holds beneath a path, relative to the repository root.
     *
     * Listed with `-z` so a path containing a space, a quote or a newline arrives as git
     * stored it instead of quoted.
     *
     * @param string $directory The checkout to ask
     * @param string $ref       A tag, branch or commit
     * @param string $path      The directory within the repository to list
     *
     * @return list<string>
     */
    public function files(string $directory, string $ref, string $path): array
    {
        $output = $this->run($directory, ['ls-tree', '-r', '-z', $ref, '--', $path]);

        if (null === $output) {
            return [];
        }

        $files = [];

        foreach (explode("\0", $output) as $entry) {
            [$meta, $file] = explode("\t", $entry, limit: 2) + [1 => ''];

            // Only blobs hold content: a submodule is listed as a commit and has none.
            if ('' !== $file && str_contains($meta, ' blob ')) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * One file as a ref holds it, byte for byte, or null when the ref has no such file.
     *
     * Not trimmed, because a file's trailing newline is part of the file.
     */
    public function show(string $directory, string $ref, string $path): ?string
    {
        return $this->run($directory, ['show', "{$ref}:{$path}"]);
    }

    /**
     * Whether a git command ran and exited cleanly, for the ones that answer nothing.
     *
     * `ask()` cannot tell a successful `clone` from a failed one, because both print nothing
     * and both come back null.
     *
     * @param list<string> $arguments The git arguments, one value each
     */
    public function succeeds(string $directory, array $arguments): bool
    {
        return null !== $this->run($directory, $arguments);
    }

    /**
     * @param list<string> $arguments
     */
    private function run(string $directory, array $arguments): ?string
    {
        if ('' === $directory) {
            return null;
        }

        try {
            return Shell\execute(
                command         : 'git',
                arguments       : $arguments,
                workingDirectory: $directory,
            );
        } catch (Shell\Exception\ExceptionInterface|Async\Exception\CancelledException) {
            return null;
        }
    }
}
