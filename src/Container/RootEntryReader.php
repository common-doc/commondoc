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
use CommonDoc\Input\Target;

use function is_array;
use function is_string;

/**
 * Reads one configured entry as a root.
 *
 * Separate from `RootReader` because deciding what one entry means is a different job
 * from walking a list of them, and keeping both in one class made the class the
 * complexity limit noticed rather than the entry.
 *
 * There are two shapes, and they answer different questions:
 *
 * - `path` names the directory to walk. Whether it exists is the walk's question, and
 *   the walk answers it with the path in the message.
 * - `repository` names a repository root, and the documentation is found inside it by
 *   the target, which the entry may override. That distinction is the point: a composed
 *   site states each repository's target once, and the repository that differs states
 *   its own.
 *
 * A `repository` entry is verified before it becomes a root. The target is the
 * operator's claim about where documentation lives, and a target that matches nothing
 * would build cleanly and publish nothing, so the claim is checked rather than trusted.
 *
 * @internal
 */
final readonly class RootEntryReader
{
    /**
     * The root this entry describes, or null when it describes none.
     *
     * @param mixed $entry Deliberately untyped: this class decides what it is
     *
     * @throws InvalidArgumentException When the entry's mount cannot prefix a path, or
     *                                  its repository holds no documentation at its target
     *
     * @infection-ignore-all The `is_array` guard is reported as an equivalent mutant
     * because `??` tests with isset(), which answers null for an offset on a scalar, so a
     * removed guard reaches the same `null` return. It is kept because it states that
     * only a shaped entry can be a root, rather than relying on that coincidence.
     */
    public function read(mixed $entry, Target $fallback): ?Root
    {
        if (! is_array($entry)) {
            return null;
        }

        /** @var mixed $mount Deliberately untyped: the constructor decides */
        $mount = $entry['mount'] ?? '/';

        if (! is_string($mount)) {
            return null;
        }

        return (
            $this->fromRepository($entry, $mount, $fallback)
                ?? $this->fromPath($entry, $mount)
        );
    }

    /**
     * The root an entry describes by naming a directory, or null when it names none.
     *
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidArgumentException When the entry's mount cannot prefix a path
     */
    private function fromPath(array $entry, string $mount): ?Root
    {
        $path = $this->text($entry['path'] ?? null);

        if (null === $path) {
            return null;
        }

        return new Root(
            path : $path,
            mount: $mount,
        );
    }

    /**
     * The root an entry describes by naming a repository, or null when it names none.
     *
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidArgumentException When the repository holds no documentation at its target
     */
    private function fromRepository(array $entry, string $mount, Target $fallback): ?Root
    {
        $repository = $this->text($entry['repository'] ?? null);

        if (null === $repository) {
            return null;
        }

        return new Root(
            path : $this->targetOf($entry, $fallback)->assertConsumable($repository),
            mount: $mount,
        );
    }

    /**
     * The target a `repository` entry asks for.
     *
     * An override is the entry's own value; the site's target is the fallback. An entry
     * whose override is of no use takes the fallback, because a mistyped override should
     * not cost that repository the whole build.
     *
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidArgumentException When the entry's target cannot name a directory
     *                                  relative to a repository root
     */
    private function targetOf(array $entry, Target $fallback): Target
    {
        $target = $this->text($entry['target'] ?? null);

        return null === $target ? $fallback : new Target($target);
    }

    /**
     * A configured value as a non-empty string, or null when it is not one.
     *
     * One place for a rule that would otherwise be three copies of the same two checks.
     *
     * @param mixed $value Deliberately untyped: configuration is
     */
    private function text(mixed $value): ?string
    {
        return is_string($value) && '' !== $value ? $value : null;
    }
}
