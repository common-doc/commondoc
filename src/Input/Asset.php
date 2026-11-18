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
 * The non-Markdown files beneath a source root.
 *
 * Images and stylesheets are published alongside the documents that reference
 * them, and a link to an image needs the same translation a link to a document
 * does. Without a list of assets those references have nothing to resolve
 * against, so the translation would either skip them or invent a path.
 *
 * Nothing is filtered beyond the Markdown extension. The traversal already
 * reports regular files only, so a socket or a device node never appears here,
 * and a file that happens to be unreadable is left in the list on purpose: it
 * will fail loudly when it is copied, which is more useful than vanishing from
 * the build with a broken link as the only symptom.
 *
 * @api
 */
final readonly class Asset
{
    private const string DOCUMENT = '.md';

    /**
     * Every asset beneath the root, ordered so two builds agree.
     *
     * @return list<non-empty-string>
     *
     * @throws InvalidArgumentException When the root is not a readable directory
     */
    public function scan(string $root): array
    {
        $assets = [];

        foreach (Tree::at($root)->paths() as $path) {
            if (Str\ends_with_ci($path, suffix: self::DOCUMENT)) {
                continue;
            }

            $assets[] = $path;
        }

        return $assets;
    }
}
