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

namespace CommonDoc\Output;

use CommonDoc\Exception\InvalidArgumentException;
use Psl\Filesystem;

use function dirname;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_starts_with;

/**
 * The directory a build writes the site into, cleared before the first file is written.
 *
 * A build that only adds files is a build whose output is the union of every build ever run
 * into it. That is invisible until something is deleted or renamed, at which point the old
 * page stays published and a deploy carries it forward. Clearing first is what makes the
 * output describe this build and nothing else.
 *
 * Clearing is destructive, so the path is refused rather than warned about when it is a
 * value that cannot be a site's output:
 *
 * - empty, `.`, or a filesystem root, which would delete the working directory or a volume;
 * - a directory that holds one of the build's own sources, which would delete the
 *   documentation the build is reading.
 *
 * What it refuses, it refuses when it is constructed, before anything has been written, so a
 * refused build never leaves a half-cleared tree behind.
 */
final readonly class OutputDirectory
{
    /**
     * @param string             $path    The directory the build writes into
     * @param list<non-empty-string> $sources The directories the build reads, which must not be
     *                                        inside the output
     *
     * @throws InvalidArgumentException When the path cannot be a site's output
     */
    public function __construct(
        private string $path,
        private array $sources = [],
    ) {
        $this->assertSafe();
    }

    /**
     * Remove everything inside the output directory, keeping the directory itself.
     *
     * The directory is kept because it may be a mount, and removing a mount point fails. A
     * directory that does not exist needs no clearing, because a build creates what it writes.
     *
     * @throws Filesystem\Exception\ExceptionInterface When the output cannot be read or a node
     *         cannot be removed
     */
    public function clear(): void
    {
        if (! Filesystem\is_directory($this->path)) {
            return;
        }

        foreach (Filesystem\read_directory($this->path) as $node) {
            if (Filesystem\is_directory($node) && ! Filesystem\is_symbolic_link($node)) {
                Filesystem\delete_directory($node, recursive: true);

                continue;
            }

            Filesystem\delete_file($node);
        }
    }

    /**
     * @throws InvalidArgumentException When the path cannot be a site's output
     */
    private function assertSafe(): void
    {
        $trimmed = rtrim($this->path, characters: '/\\');

        // A root refuses itself: its own parent is itself, and on Windows a drive root is
        // left as `C:` once the separator is trimmed, which names the same place.
        $isRoot =
            '' === $trimmed
            || '.' === $trimmed
            || dirname($trimmed) === $trimmed
            || 1 === preg_match('~^[A-Za-z]:$~', $trimmed);

        if ($isRoot) {
            throw new InvalidArgumentException(sprintf(
                'The output directory "%s" cannot be cleared: it is a filesystem root or the working directory.',
                $this->path,
            ));
        }

        foreach ($this->sources as $source) {
            if ($this->contains($source)) {
                throw new InvalidArgumentException(sprintf(
                    'The output directory "%s" holds the source "%s", so clearing it would delete the documentation it reads.',
                    $this->path,
                    $source,
                ));
            }
        }
    }

    /**
     * Whether a source sits inside the output, compared with one separator so a path written
     * with either one is answered the same.
     */
    private function contains(string $source): bool
    {
        $directory = str_replace(
            search : '\\',
            replace: '/',
            subject: rtrim($this->path, characters: '/\\'),
        );
        $target = str_replace(
            search : '\\',
            replace: '/',
            subject: rtrim($source, characters: '/\\'),
        );

        return str_starts_with("{$target}/", "{$directory}/");
    }
}
