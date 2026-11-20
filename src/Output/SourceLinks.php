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

use CommonDoc\Exception\RuntimeException;
use CommonDoc\Input\Source;

use function ltrim;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Where a target that left the published site points inside the repository it came from.
 *
 * A document may link to something it does not publish: a benchmark result, a workflow
 * file, a sibling directory. Those targets are not on the site at all, but they are in
 * the repository, and pointing at it keeps a source tree publishable without editing the
 * links it already had.
 *
 * The target is resolved here a second time, in repository space rather than site space.
 * Site space cannot answer this question: a resolved site path has already spent its
 * mount, so it no longer says how many segments it climbed and the repository path would
 * be a guess.
 *
 * @internal
 */
final readonly class SourceLinks
{
    private const string SEPARATOR = '/';

    /**
     * @param array<non-empty-string, Source> $origins Site path prefix to the repository holding it
     */
    public function __construct(
        private array $origins = [],
    ) {}

    /**
     * The repository URL a target resolves to, or null when it stays inside the
     * source root.
     *
     * @param string $path The target exactly as the document wrote it
     * @param string $from The local path of the document holding the link
     */
    public function at(string $path, string $from): ?string
    {
        $match = $this->matchOf($from);

        if (null === $match) {
            return null;
        }

        [$mount, $source] = $match;

        if (str_starts_with($path, self::SEPARATOR)) {
            // An absolute target is relative to the site root, which is the only
            // absolute space a reader knows. Nothing about the repository applies.
            return null;
        }

        $at     = substr($from, offset: strlen($mount));
        $inRepo = '' === $source->prefix ? $at : $source->prefix . self::SEPARATOR . $at;

        try {
            $resolved = SourcePath::resolve($path, $inRepo);
        } catch (RuntimeException) {
            // The target climbed above the repository, so there is no file to link
            // to and the caller's own refusal is the honest answer.
            return null;
        }

        if ('' === $source->prefix || str_starts_with($resolved, $source->prefix . self::SEPARATOR)) {
            // Still inside the root that was walked, so this is a genuinely dead
            // link rather than a link to the source.
            return null;
        }

        return $source->at($resolved);
    }

    /**
     * The mount a document was published from, and the repository behind it.
     *
     * A mount carries a leading slash and a source path does not, so the two are
     * compared without it. The longest matching mount wins, because one mount may be
     * a prefix of another (`/` and `/guide/`) and the document belongs to the more
     * specific one rather than to the site root.
     *
     * @param string $from The local path of the document
     *
     * @return array{string, Source}|null Null when no mount carries a repository,
     *         which leaves the caller's own refusal in place
     */
    private function matchOf(string $from): ?array
    {
        $match  = null;
        $length = -1;

        foreach ($this->origins as $mount => $source) {
            $prefix = ltrim($mount, characters: self::SEPARATOR);

            if (str_starts_with($from, $prefix) && strlen($prefix) > $length) {
                $length = strlen($prefix);
                $match  = [$prefix, $source];
            }
        }

        return $match;
    }
}
