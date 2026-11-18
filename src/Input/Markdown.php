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

use Psl\Str;

/**
 * The Markdown documents in a list of paths.
 *
 * It answers one question: which of these files are documents. It knows nothing
 * about output paths or published paths, so the translation stays in one place
 * rather than being spread across every type that happens to need a file list.
 *
 * It takes the paths rather than a root, because walking the tree is the
 * expensive part and doing it per type would walk once per type. The caller
 * walks once and hands the same list to every filter, so adding a type costs one
 * pass over an array rather than another pass over the filesystem.
 *
 * Taking a list also makes this a pure function of its input: no directory to
 * refuse, no temporary tree needed to test it.
 *
 * Paths are returned relative to the root with forward slashes, so a value is
 * safe to record, compare and use as a route name on any platform.
 *
 * @api
 */
final readonly class Markdown
{
    /**
     * The suffix that makes a file a document.
     *
     * Public because the translation has to agree with this filter about what a
     * document is. A second definition elsewhere would be a rule with two
     * copies, and the copies would drift the first time this one changed.
     */
    public const string EXTENSION = '.md';

    /**
     * The documents among the given paths, in the order they arrive.
     *
     * Order is inherited rather than imposed: `Tree::paths()` already sorts by
     * byte value, and sorting again here would be work that changes nothing.
     *
     * The extension is matched without regard to case. A document named `.MD` is
     * a document, and skipping it silently would publish a site missing a
     * document, which is worse than the duplicate it might create: two paths
     * that differ only in extension produce one output file, and that collision
     * is reported when the routes are registered rather than hidden here.
     *
     * @param list<non-empty-string> $paths Every file beneath the source root
     *
     * @return list<non-empty-string>
     */
    public function scan(array $paths): array
    {
        $documents = [];

        foreach ($paths as $path) {
            if (! Str\ends_with_ci($path, suffix: self::EXTENSION)) {
                continue;
            }

            $documents[] = $path;
        }

        return $documents;
    }
}
