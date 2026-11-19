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

namespace CommonDoc\Container;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;

use function is_array;
use function is_string;

/**
 * Reads the site's source roots out of the configuration.
 *
 * Its own collaborator because reading a list is a different job from reading a
 * scalar: a scalar has one rule, while an entry has a shape, a mount that the type
 * validates, and a fallback that must not discard the entries that were correct.
 *
 * An entry that cannot name a directory is treated as a typo and dropped, so one
 * wrong entry costs itself rather than the whole list. A mount that cannot prefix an
 * address is not a typo in the same sense: `Root` refuses it with the value in the
 * message, because a build that published at an address the operator did not write
 * would be worse than one that refused to start.
 *
 * Whether a directory exists is deliberately not this class's question. The walk
 * answers that with the path in the message, which is where an operator can act on
 * it.
 *
 * @internal
 */
final readonly class RootReader
{
    /**
     * The default site: the sources this package documents itself from.
     *
     * A library default cannot know a deployment's layout, so it states the one
     * layout that is true of this repository and is a working example for any other.
     * A deployment overrides it in its own autoload file or in the manifest a
     * workflow writes.
     *
     * @return list<Root>
     *
     * @throws InvalidArgumentException When the default root cannot be constructed
     */
    public function defaults(): array
    {
        return [new Root(path: 'docs/v1')];
    }

    /**
     * The configured roots, or the default list when none of them is usable.
     *
     * @param mixed $configured The configured list, untyped because configuration is
     *
     * @return list<Root>
     *
     * @throws InvalidArgumentException When an entry names a mount that cannot prefix
     *         an address
     */
    public function read(mixed $configured): array
    {
        if (! is_array($configured)) {
            return $this->defaults();
        }

        $roots = [];

        /** @var mixed $entry Deliberately untyped: the checks below decide what it is */
        foreach ($configured as $entry) {
            $root = $this->one($entry);

            if (null === $root) {
                continue;
            }

            $roots[] = $root;
        }

        return [] === $roots ? $this->defaults() : $roots;
    }

    /**
     * One configured entry, or null when it cannot name a root.
     *
     * @param mixed $entry Deliberately untyped: this method decides what it is
     *
     * @throws InvalidArgumentException When the entry's mount cannot prefix an address
     *
     * @infection-ignore-all The `is_array` guard is reported as an equivalent mutant
     * because `??` tests with isset(), which answers null for an offset on a scalar,
     * so a removed guard reaches the same `null` return. It is kept because it states
     * that only a shaped entry can be a root, rather than relying on that coincidence.
     */
    private function one(mixed $entry): ?Root
    {
        if (! is_array($entry)) {
            return null;
        }

        /** @var mixed $path Deliberately untyped: checked next */
        $path = $entry['path'] ?? null;

        if (! is_string($path) || '' === $path) {
            return null;
        }

        /** @var mixed $mount Deliberately untyped: the constructor decides */
        $mount = $entry['mount'] ?? '/';

        if (! is_string($mount)) {
            return null;
        }

        return new Root(
            path : $path,
            mount: $mount,
        );
    }
}
