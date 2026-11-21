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

use CommonDoc\Input\Root;

use function str_starts_with;
use function strlen;

/**
 * Which menu a document belongs to.
 *
 * A site holds several components, each with several versions, and one menu over all of
 * them is unusable. Each root gets a menu of its own, named from its mount, so a page
 * shows the pages of the component and version it is in, and the route registration and
 * the page agree on the name because both ask here.
 *
 * The longest matching mount wins, so a root mounted inside another still owns its files.
 * A document no root holds falls back to the base identifier.
 *
 * @api
 */
final readonly class NavigationScope
{
    /** The base identifier: a document no root claims, and the prefix every scope extends. */
    public const string BASE = 'commondoc';

    /**
     * @param list<Root> $roots Every root the site is composed from
     */
    public function __construct(
        private array $roots,
    ) {}

    /**
     * @param non-empty-string $sitePath The document's site path
     *
     * @return non-empty-string
     */
    public function of(string $sitePath): string
    {
        $mount = null;

        foreach ($this->roots as $root) {
            if (! str_starts_with("/{$sitePath}", $root->mount)) {
                continue;
            }

            if (null === $mount || strlen($root->mount) > strlen($mount)) {
                $mount = $root->mount;
            }
        }

        return null === $mount ? self::BASE : self::BASE . ":{$mount}";
    }
}
