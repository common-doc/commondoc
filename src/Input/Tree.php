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
use Psl\Filesystem;
use Psl\Str;

use function array_push;
use function rtrim;
use function sort;
use function sprintf;

use const SORT_STRING;

/**
 * Every file beneath a source root, named relative to it.
 *
 * One traversal serves every input type, so a document scan and an asset scan
 * cannot disagree about what exists or how a path is spelled. Each of them adds
 * only its own filter on top.
 *
 * The traversal is `Psl\Filesystem`, the same library `FileWriter` writes
 * through, so the types are declared by the library rather than inferred from a
 * native iterator, and an unreadable directory is a typed refusal instead of a
 * warning and an empty list.
 *
 * Hidden entries are skipped rather than absorbed and filtered later: a `.git`
 * directory is a source of noise at best and of enormous file counts at worst,
 * and it is never part of a documentation site. A symbolic link to a directory
 * is not descended into, so a link cycle cannot make a scan run forever, while a
 * symbolic link to a file is still published.
 *
 * @api
 */
final readonly class Tree
{
    private const string HIDDEN_PREFIX = '.';

    private const string SEPARATORS = '/\\';

    /**
     * @param non-empty-string $root Without a trailing separator
     */
    private function __construct(
        private string $root,
    ) {}

    /**
     * The tree beneath a root, or a typed refusal when the root cannot be read.
     *
     * @param string $root A directory that exists and is readable
     *
     * @throws InvalidArgumentException When the root is not a readable directory
     */
    public static function at(string $root): self
    {
        if ('' === $root || ! Filesystem\is_directory($root)) {
            throw new InvalidArgumentException(sprintf(
                'The source root "%s" is not a directory.',
                $root,
            ));
        }

        if (! Filesystem\is_readable($root)) {
            throw new InvalidArgumentException(sprintf(
                'The source root "%s" is not readable.',
                $root,
            ));
        }

        /** @var non-empty-string $trimmed */
        $trimmed = rtrim($root, characters: self::SEPARATORS);

        return new self($trimmed);
    }

    /**
     * Every file beneath the root, ordered so two builds agree.
     *
     * Ordering is sorted rather than inherited from the filesystem, because
     * directory order differs between platforms and a report that reordered
     * itself between runs would be useless to diff.
     *
     * @return list<non-empty-string>
     *
     * @throws Filesystem\Exception\NotFoundException         When a directory disappears mid-walk
     * @throws Filesystem\Exception\NotDirectoryException     When an entry stops being a directory
     * @throws Filesystem\Exception\NotReadableException      When a directory becomes unreadable
     */
    public function paths(): array
    {
        $paths = $this->collect($this->root, prefix: '');

        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * The files beneath one directory, named relative to the root.
     *
     * The three refusals are the library's own. They extend
     * `InvalidArgumentException`, so a caller that catches that still catches
     * them, and declaring them here is what keeps the failure vocabulary typed
     * instead of collapsing into one untyped throw.
     *
     * @param non-empty-string $directory
     *
     * @return list<non-empty-string>
     *
     * @throws Filesystem\Exception\NotFoundException     When the directory disappears mid-walk
     * @throws Filesystem\Exception\NotDirectoryException When the entry stops being a directory
     * @throws Filesystem\Exception\NotReadableException  When the directory becomes unreadable
     */
    private function collect(string $directory, string $prefix): array
    {
        $paths = [];

        foreach (Filesystem\read_directory($directory) as $entry) {
            $name = Filesystem\get_basename($entry);

            if (Str\starts_with($name, self::HIDDEN_PREFIX)) {
                continue;
            }

            $relative = '' === $prefix ? $name : "{$prefix}/{$name}";

            if (Filesystem\is_directory($entry) && ! Filesystem\is_symbolic_link($entry)) {
                array_push($paths, ...$this->collect($entry, $relative));

                continue;
            }

            if (Filesystem\is_file($entry)) {
                $paths[] = $relative;
            }
        }

        return $paths;
    }
}
