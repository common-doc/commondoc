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

use CommonDoc\Exception\InvalidArgumentException;
use Override;
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
final readonly class VersionEntryReader implements EntryReaderInterface
{
    /**
     * @param mixed        $entry     Deliberately untyped: configuration is
     * @param list<Target> $fallbacks The site's targets, used when the entry states none
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
    #[Override]
    public function read(mixed $entry, array $fallbacks): ?array
    {
        if (! is_array($entry) || true !== ($entry['versions'] ?? null)) {
            return null;
        }

        /** @var mixed $repository Deliberately untyped: the check below decides what it is */
        $repository = $entry['repository'] ?? null;

        if (! is_string($repository) || '' === $repository) {
            return null;
        }

        $targets = new TargetReader()->read($entry['target'] ?? null);

        return new VersionRoot()->of(
            repository: $repository,
            targets   : [] === $targets ? $fallbacks : $targets,
            workspace : sys_get_temp_dir() . '/commondoc-versions',
        );
    }
}
