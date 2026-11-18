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

use InvalidArgumentException;
use Psl\Str;

/**
 * The Markdown documents beneath a source root.
 *
 * It answers one question: which documents exist. It knows nothing about output
 * paths or published paths, so the translation stays in one place rather than
 * being spread across every type that happens to need a file list.
 *
 * Paths are returned relative to the root with forward slashes, so a value is
 * safe to record, compare and use as a route name on any platform.
 *
 * @api
 */
final readonly class Markdown
{
    private const string EXTENSION = '.md';

    /**
     * Every `.md` file beneath the root, ordered so two builds agree.
     *
     * The extension is matched without regard to case. A document named `.MD` is
     * a document, and skipping it silently would publish a site missing a
     * document, which is worse than the duplicate it might create: two paths
     * that differ only in extension produce one output file, and that collision
     * is reported when the routes are registered rather than hidden here.
     *
     * @return list<non-empty-string>
     *
     * @throws InvalidArgumentException When the root is not a readable directory
     */
    public function scan(string $root): array
    {
        $documents = [];

        foreach (Tree::at($root)->paths() as $path) {
            if (! Str\ends_with_ci($path, suffix: self::EXTENSION)) {
                continue;
            }

            $documents[] = $path;
        }
        return $documents;
    }
}
