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

use function array_keys;
use function is_string;
use function ksort;
use function sprintf;

use const SORT_STRING;

/**
 * What a build found, and where each file is on disk.
 *
 * Discovery is cross-document work: it answers a question no single conversion can
 * answer, and CommonMark has no vocabulary for a file, a directory or a site. So it
 * is a pass of its own, and this is that pass's result.
 *
 * A path here is relative to the site, not to the repository it came from. The mount
 * was applied while walking, so everything downstream sees one path space and
 * nothing below this type knows how many repositories were read.
 *
 * The mapping is the only place a site path and a disk path are related. A document
 * is read from its source and written at its published path, and both come from here, so
 * they cannot be confused for one another.
 *
 * @api
 */
final readonly class Scan
{
    private const string SEPARATOR = '/';

    /**
     * @param array<non-empty-string, non-empty-string> $sources   Site path to the file that holds it
     * @param list<non-empty-string>                    $documents The site paths that are documents
     * @param list<non-empty-string>                    $assets    The site paths that are not
     * @param array<non-empty-string, Source>           $origins   Site path prefix to the repository that holds it
     */
    public function __construct(
        public array $sources,
        public array $documents,
        public array $assets,
        public array $origins = [],
    ) {}

    /**
     * Every file beneath every root, named by the site path it is published at.
     *
     * The walk is once per root and the filters run once over the merged result, so
     * adding a root costs a walk rather than a second traversal of the site. Ordering
     * is imposed rather than inherited, because two roots are walked separately and
     * the merged list would otherwise depend on configuration order: a report that
     * reordered itself between runs would be useless to diff.
     *
     * @param list<Root> $roots
     *
     * @throws InvalidArgumentException When a root is not a readable directory
     * @throws Filesystem\Exception\NotFoundException When a directory disappears mid-walk
     * @throws Filesystem\Exception\NotDirectoryException When an entry stops being a directory
     * @throws Filesystem\Exception\NotReadableException When a directory becomes unreadable
     */
    public static function of(array $roots): self
    {
        $sources = [];
        $origins = [];
        $reader  = new SourceReader();

        foreach ($roots as $root) {
            // Root guarantees the directory carries no trailing separator, so the
            // join here has one rule rather than a defensive second normalisation
            // that no caller could reach.
            $directory = $root->directory();

            $source = $reader->read($directory);

            if (null !== $source) {
                $origins[$root->mount] = $source;
            }

            foreach (Tree::at($directory)->paths() as $path) {
                $sources[$root->mountPath($path)] = sprintf('%s%s%s', $directory, self::SEPARATOR, $path);
            }
        }

        // Sorted rather than in configuration order: two roots are walked
        // separately, so the merged list would otherwise depend on how the site was
        // written down, and a report that reordered itself between runs would be
        // useless to diff.
        ksort($sources, SORT_STRING);

        $paths     = array_keys($sources);
        $documents = new Markdown()->scan($paths);

        return new self(
            sources  : $sources,
            documents: $documents,
            assets   : new Asset()->scan(
                paths    : $paths,
                documents: $documents,
            ),
            origins  : $origins,
        );
    }

    /**
     * Where one site path is on disk.
     *
     * A miss is an invariant failure in this package rather than anything a
     * deployment can cause: every path this type reports came from the walk that
     * built the map. It refuses rather than answering with an index that reads null,
     * because an unreadable path reaching the reader as a `TypeError` would be
     * reported as a problem with a document that is fine.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When the path is not one this scan found
     */
    public function sourceOf(string $path): string
    {
        /** @var mixed $file Deliberately untyped: the check below decides what it is */
        $file = $this->sources[$path] ?? null;

        if (! is_string($file) || '' === $file) {
            throw new InvalidArgumentException(sprintf(
                'The path "%s" was not found by the scan, which is a defect in this package rather than in the documentation.',
                $path,
            ));
        }

        return $file;
    }
}
