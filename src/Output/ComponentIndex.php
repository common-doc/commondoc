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
use CommonDoc\Input\Component;
use CommonDoc\Input\Root;

use function ltrim;
use function trim;

/**
 * The components a site documents, one entry each, answered from its roots.
 *
 * The sidebar and the home page both list components, so they ask here and cannot
 * disagree about which exist. A released component is one entry however many versions it
 * has, pointing at its newest. A root that is not a release is one entry too, named from
 * its mount, unless it is mounted at the site root: that root is the site itself and
 * there is nothing to link to that the site's own address does not already do.
 *
 * @type Entry = array{title: string, index: non-empty-string, target: non-empty-string, latest: string, segment: ?string}
 *
 * @api
 */
final readonly class ComponentIndex
{
    private const string INDEX = 'index.md';

    /**
     * @param list<Root> $roots Every root the site is composed from
     */
    public function __construct(
        private array $roots,
    ) {}

    /**
     * Every component, released ones first in newest-first order, then the others as configured.
     *
     * `index` is the component's own address, a local path a URL can be generated for. For a
     * released component it redirects to the newest version and `segment` names it. `target`
     * is the page a reader lands on: the newest version's index for a released component,
     * and the root's own index for any other, where `segment` is null and `index` is the same.
     *
     * @return list<Entry>
     *
     * @throws InvalidArgumentException When a mount names a component that cannot be a path segment
     */
    public function entries(): array
    {
        $menu    = new VersionMenu($this->roots);
        $entries = [];

        foreach ($menu->latest() as $segment => $root) {
            $entries[] = [
                'title'   => $root->release?->component->label ?? $segment,
                'index'   => "{$segment}/" . self::INDEX,
                'target'  => ltrim($root->mount, characters: '/') . self::INDEX,
                'latest'  => $root->release?->version->tag ?? '',
                'segment' => $segment,
            ];
        }

        foreach ($this->roots as $root) {
            $name = trim($root->mount, characters: '/');

            if (null !== $root->release || '' === $name) {
                continue;
            }

            $index = ltrim($root->mount, characters: '/') . self::INDEX;

            $entries[] = [
                'title'   => new Component($name)->label,
                'index'   => $index,
                'target'  => $index,
                'latest'  => '',
                'segment' => null,
            ];
        }

        return $entries;
    }
}
