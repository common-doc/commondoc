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

namespace CommonDoc\Container;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use CommonDoc\Input\Target;
use CommonDoc\Input\VersionRoot;
use Psl\Filesystem;
use Psl\Regex;
use Psl\Type;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;

use function is_array;
use function is_string;
use function sys_get_temp_dir;

/**
 * Reads one configured entry as the roots of a repository's released versions.
 *
 * Separate from `RootEntryReader` because it answers a different question: that reader
 * makes one root from an entry, and this one makes one root per tag.
 *
 * An entry opts in with `'versions' => true`. Its mount is not read, because each
 * version is mounted at `/{component}/v{major}/`, which is what makes the versions a set.
 * Its `target` may be one directory or a list tried in order for each tag.
 *
 * @internal
 */
final readonly class VersionEntryReader
{
    /**
     * @param mixed $entry Deliberately untyped: configuration is
     *
     * @return ?list<Root> Null when the entry does not ask for versions
     *
     * @throws InvalidArgumentException When a mount cannot prefix a path
     * @throws FileWriterException When a version's files cannot be written
     * @throws Filesystem\Exception\ExceptionInterface When a previous extraction cannot be cleared
     * @throws Regex\Exception\ExceptionInterface When a tag cannot be matched, which the fixed
     *         pattern makes unreachable
     * @throws Type\Exception\ExceptionInterface When the captured groups are not the shape the
     *         pattern promises, which the fixed pattern makes unreachable
     */
    public function read(mixed $entry, Target $fallback): ?array
    {
        if (! is_array($entry) || true !== ($entry['versions'] ?? null)) {
            return null;
        }

        /** @var mixed $repository Deliberately untyped: the check below decides what it is */
        $repository = $entry['repository'] ?? null;

        if (! is_string($repository) || '' === $repository) {
            return null;
        }

        $targets = $this->targets($entry['target'] ?? null);

        return new VersionRoot()->of(
            repository: $repository,
            targets   : [] === $targets ? [$fallback] : $targets,
            workspace : sys_get_temp_dir() . '/commondoc-versions',
        );
    }

    /**
     * @param mixed $value Deliberately untyped: configuration is
     *
     * @return list<Target>
     *
     * @throws InvalidArgumentException When a candidate cannot name a directory
     */
    private function targets(mixed $value): array
    {
        $targets = [];

        /** @var mixed $candidate Deliberately untyped: the check below decides what it is */
        foreach (is_array($value) ? $value : [$value] as $candidate) {
            if (! (is_string($candidate) && '' !== $candidate)) {
                continue;
            }

            $targets[] = new Target($candidate);
        }

        return $targets;
    }
}
