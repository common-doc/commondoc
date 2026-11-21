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

use Psl\Regex;
use Psl\Type;

use function preg_split;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Reads the versions a checkout has released, from its git tags.
 *
 * The versions are read from the repository rather than configured. The tags are the
 * record of what was released, and a declared list could disagree with them in exactly
 * the case nobody would notice: a release tagged after the configuration was written.
 *
 * A directory that is not a checkout, or has no tags, answers an empty list rather than
 * failing, which is the ordinary shape of a `path` root assembled outside a repository.
 *
 * @internal
 */
final readonly class VersionReader
{
    public function __construct(
        private Git $git = new Git(),
    ) {}

    /**
     * The latest version of each major line the checkout has tagged.
     *
     * @param string $directory The checkout to read
     *
     * @return list<Version>
     *
     * @throws Regex\Exception\ExceptionInterface When a tag cannot be matched, which the fixed
     *         pattern makes unreachable
     * @throws Type\Exception\ExceptionInterface When the captured groups are not the shape the
     *         pattern promises, which the fixed pattern makes unreachable
     */
    public function read(string $directory): array
    {
        $tags = $this->git->ask($directory, ['tag', '--list']);

        if (null === $tags) {
            return [];
        }

        // Any line ending, because git on Windows answers with one that is not `\n`.
        $names = preg_split(
            pattern: '~\R~',
            subject: $tags,
            flags  : PREG_SPLIT_NO_EMPTY,
        );

        return Version::latestOfEachMajor(false === $names ? [] : $names);
    }
}
