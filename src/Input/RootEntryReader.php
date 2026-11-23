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
use Override;

use function is_array;
use function is_string;
use function sprintf;

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
// @mago-expect lint:cyclomatic-complexity
final readonly class RootEntryReader implements EntryReaderInterface
{
    /**
     * The roots this entry describes, which is at most one.
     *
     * A list because that is what every entry reader answers, so the reader walking the
     * configured entries does not branch on which kind it holds. `null` means the entry is
     * not this reader's kind rather than that it described nothing.
     *
     * @param mixed        $entry     Deliberately untyped: this class decides what it is
     * @param list<Target> $fallbacks The site's targets, used by an entry that states none
     *
     * @return ?list<Root> Null when the entry names neither a repository nor a path
     *
     * @throws InvalidArgumentException When the entry's mount cannot prefix a path, or
     *                                  its repository holds no documentation at any of
     *                                  the candidate targets
     *
     * @infection-ignore-all The `is_array` guard in `root()` is reported as an equivalent
     * mutant because `??` tests with isset(), which answers null for an offset on a scalar,
     * so a removed guard reaches the same `null` return. It is kept because it states that
     * only a shaped entry can be a root, rather than relying on that coincidence.
     */
    #[Override]
    public function read(mixed $entry, array $fallbacks): ?array
    {
        $root = $this->root($entry, $fallbacks);

        return null === $root ? null : [$root];
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
     * @param list<Target>            $fallbacks
     *
     * @throws InvalidArgumentException When the repository holds no documentation at any
     *                                  candidate target
     */
    private function fromRepository(array $entry, string $mount, array $fallbacks): ?Root
    {
        $repository = $this->text($entry['repository'] ?? null);

        if (null === $repository) {
            return null;
        }

        return new Root(
            path : $this->targetOf($entry, $fallbacks, $repository),
            mount: $mount,
        );
    }

    /**
     * The root this entry describes, or null when it describes none.
     *
     * @param mixed        $entry     Deliberately untyped: this class decides what it is
     * @param list<Target> $fallbacks
     *
     * @throws InvalidArgumentException When the entry's mount cannot prefix a path, or
     *                                  its repository holds no documentation at any of
     *                                  the candidate targets
     */
    private function root(mixed $entry, array $fallbacks): ?Root
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
            $this->fromRepository($entry, $mount, $fallbacks)
                ?? $this->fromPath($entry, $mount)
        );
    }

    /**
     * The directory a `repository` entry is read from.
     *
     * The entry's own value is the answer when it states one, and the site's targets are the
     * fallback, so a mistyped override does not cost that repository the whole build. Either
     * may name a list, tried in order: a repository whose layout changed keeps one directory
     * for older releases and one for newer, and the first directory that exists is the one
     * it is read from. When none of them exists the last refusal is raised, which names the
     * directory the operator wrote last.
     *
     * @param array<array-key, mixed> $entry
     * @param list<Target>            $fallbacks
     *
     * @throws InvalidArgumentException When the repository holds no documentation at any
     *                                  candidate, or there is no candidate at all
     */
    private function targetOf(array $entry, array $fallbacks, string $repository): string
    {
        $own        = new TargetReader()->read($entry['target'] ?? null);
        $candidates = [] === $own ? $fallbacks : $own;
        $refusal    = null;

        foreach ($candidates as $candidate) {
            try {
                return $candidate->assertConsumable($repository);
            } catch (InvalidArgumentException $thrown) {
                $refusal = $thrown;
            }
        }

        if (null === $refusal) {
            throw new InvalidArgumentException(sprintf(
                'The repository "%s" states no target, and the site states none either, so there is no directory to read it from.',
                $repository,
            ));
        }

        throw $refusal;
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
