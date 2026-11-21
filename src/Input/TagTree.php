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
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;
use Webware\FileWriter\FileWriter;

use function rtrim;

use const DIRECTORY_SEPARATOR;

/**
 * Writes one ref's documentation tree to a directory, so a version can be built from
 * what was released rather than from whatever the working copy holds.
 *
 * The files are read from git's object store, so the checkout is never switched and every
 * version of a repository can be built in one run. Each file keeps its path from the
 * repository root, so the directory it is written to is laid out like a checkout and the
 * scan and conversion that read a checkout read it unchanged.
 *
 * @internal
 */
final readonly class TagTree
{
    public function __construct(
        private Git $git = new Git(),
    ) {}

    /**
     * @param string $directory   The checkout holding the ref
     * @param string $ref         A tag, branch or commit
     * @param string $path        The documentation directory within the repository
     * @param string $destination Where the tree is written
     *
     * @return int The number of files written, so a ref with no such directory is visible
     *         to the caller rather than an empty site
     *
     * @throws FileWriterException When a file cannot be written
     * @throws Filesystem\Exception\ExceptionInterface When a previous extraction cannot be cleared
     */
    public function extract(string $directory, string $ref, string $path, string $destination): int
    {
        // Cleared first, so the destination holds exactly what the ref holds and a rebuild
        // never publishes a file a tag no longer has.
        if (Filesystem\is_directory($destination)) {
            Filesystem\delete_directory(
                directory: $destination,
                recursive: true,
            );
        }

        $written = 0;

        foreach ($this->git->files($directory, $ref, $path) as $file) {
            $content = $this->git->show($directory, ref: $ref, path: $file) ?? '';

            FileWriter::writeTree(rtrim($destination, characters: '/\\') . DIRECTORY_SEPARATOR . $file, $content);

            $written++;
        }

        return $written;
    }
}
