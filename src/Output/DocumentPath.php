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

use function array_pop;
use function explode;
use function implode;
use function in_array;
use function strtolower;

/**
 * The rule that turns a document's own path into the file written and the address served.
 *
 * Separate from `DocumentRoot` because it is a rule about a path, not about where a site is
 * mounted, and because the two answers have to be produced together: a published path and
 * the file that serves it disagreeing is a dead link that looks correct on disk.
 *
 * The rule is one thing and it is stated once: a document named for the directory index is
 * the index of its directory, not a document of its own. `README.md` is that name, and
 * `index.md` is accepted after it, because GitHub renders a directory's `README.md` and
 * ignores an `index.md`, while tags already cut carry whichever name they were cut with.
 *
 * In `PathType::Directory` the index is written as the directory's `index.html`. In
 * `PathType::File` the file itself is the address, so a `README.md` is still written as
 * `index.html`: that is the name a server resolves for the directory, and serving it under
 * its own name would leave `/guide/` with nothing to answer.
 */
final readonly class DocumentPath
{
    /** The file name a directory is served by. */
    private const string INDEX = 'index';

    /** The names a document has to have to be its directory's index, without the suffix. */
    private const array DIRECTORY_INDEX = ['readme', 'index'];

    public function __construct(
        private PathType $pathType = PathType::Directory,
    ) {}

    /**
     * The file a directory's index is written to, which is never empty: it always ends in
     * the index file's own name.
     *
     * @return non-empty-string
     */
    private static function indexFile(string $directory): string
    {
        return '' === $directory
            ? self::INDEX . '.html'
            : "{$directory}/" . self::INDEX . '.html';
    }

    /**
     * The file a document is written to, relative to the output directory.
     *
     * @return non-empty-string
     */
    public function file(string $relative): string
    {
        [$directory, $isIndex] = $this->split($relative);

        if ($isIndex || PathType::File !== $this->pathType) {
            return self::indexFile($directory);
        }

        return "{$directory}.html";
    }

    /**
     * Whether a document is the index of its own directory.
     */
    public function isIndex(string $relative): bool
    {
        [, $isIndex] = $this->split($relative);

        return $isIndex;
    }

    /**
     * The value of the route's `{path}` parameter: not a URL, and empty for the site root.
     */
    public function pathOf(string $relative): string
    {
        [$directory, $isIndex] = $this->split($relative);

        if ($isIndex) {
            return $this->indexUrl($directory);
        }

        return PathType::File === $this->pathType ? "{$directory}.html" : "{$directory}/";
    }

    private function indexUrl(string $directory): string
    {
        if (PathType::File === $this->pathType) {
            return self::indexFile($directory);
        }

        return '' === $directory ? '' : "{$directory}/";
    }

    /**
     * The directory a document belongs to, and whether the document is that directory's index.
     *
     * @return array{0: string, 1: bool}
     */
    private function split(string $relative): array
    {
        $segments = explode('/', $relative);
        $last     = array_pop($segments);

        if (! in_array(strtolower($last), self::DIRECTORY_INDEX, strict: true)) {
            return [$relative, false];
        }

        return [implode('/', $segments), true];
    }
}
