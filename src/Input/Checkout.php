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

use Psl\Filesystem;

use function sprintf;

/**
 * Puts a repository on disk, with every tag, so its versions can be read.
 *
 * A repository that is already there is brought up to date rather than cloned again, so
 * a second build costs a fetch. The clone uses whatever git credentials the environment
 * already has, so a private repository works where git can read it and no token is ever
 * written into a remote URL.
 *
 * @internal
 */
final readonly class Checkout
{
    public function __construct(
        private Git $git = new Git(),
    ) {}

    /**
     * @param non-empty-string $workspace The directory the repository is placed in, by its own name
     *
     * @return ?string The checkout's directory, or null when git could not provide it
     *
     * @throws Filesystem\Exception\ExceptionInterface When the workspace cannot be created
     */
    public function of(Candidate $repository, string $workspace): ?string
    {
        $directory = sprintf('%s/%s', $workspace, $repository->name);

        if (Filesystem\is_directory("{$directory}/.git")) {
            return $this->git->succeeds($directory, ['fetch', '--quiet', '--tags', '--force', 'origin'])
                ? $directory
                : null;
        }

        Filesystem\create_directory($workspace);

        return $this->git->succeeds($workspace, ['clone', '--quiet', $repository->url, $repository->name])
            ? $directory
            : null;
    }
}
