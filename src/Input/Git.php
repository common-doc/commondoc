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
use Psl\Str;

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
        if ('' === $directory) {
            return null;
        }

        try {
            $output = Shell\execute(
                command         : 'git',
                arguments       : $arguments,
                workingDirectory: $directory,
            );

            $output = Str\trim($output);
        } catch (
            Shell\Exception\ExceptionInterface|Async\Exception\CancelledException|Str\Exception\InvalidArgumentException
        ) {
            return null;
        }

        return '' === $output ? null : $output;
    }
}
