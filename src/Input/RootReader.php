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
use CommonDoc\Exception\RuntimeException;
use InvalidArgumentException as NativeInvalidArgumentException;
use Psl\Filesystem;
use Psl\Json;
use Psl\Regex;
use Psl\Type;
use Psr\Http\Client\ClientExceptionInterface;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;

use function array_push;
use function is_array;

/**
 * Reads the site's source roots out of the configuration.
 *
 * Its own collaborator because reading a list is a different job from reading a scalar:
 * a scalar has one rule, while an entry has a shape, a mount that the type validates,
 * and a fallback that must not discard the entries that were correct. Deciding what one
 * entry means is `RootEntryReader`'s job, for the same reason one level down.
 *
 * An entry that cannot name a directory is treated as a typo and dropped, so one wrong
 * entry costs itself rather than the whole list. A mount that cannot prefix a path
 * is not a typo in the same sense: `Root` refuses it with the value in the message,
 * because a build that published at a path the operator did not write would be
 * worse than one that refused to start.
 *
 * @internal
 */
final readonly class RootReader
{
    /**
     * The default site: this repository's own documentation.
     *
     * A library default cannot know a deployment's layout, so it states the one layout
     * that is true of this repository and is a working example for any other. A
     * deployment overrides it in its own autoload file or in the manifest a workflow
     * writes.
     *
     * It names a repository root of `.` rather than a directory, so the default resolves
     * through the same code every composed site uses.
     *
     * @return list<Root>
     *
     * @throws InvalidArgumentException When the default root cannot be constructed
     */
    public function defaults(): array
    {
        return [new Root(path: new Target()->at(repositoryRoot: '.'))];
    }

    /**
     * The configured roots, or the default list when none of them is usable.
     *
     * @param mixed   $configured The configured list, untyped because configuration is
     * @param ?Target $fallback   The site's target, used by every `repository` entry that
     *                            states no target of its own
     *
     * @return list<Root>
     *
     * @throws InvalidArgumentException When an entry names a mount that cannot prefix a
     *                                  path, or a repository whose documentation is not
     *                                  at its target
     * @throws FileWriterException When a version's files cannot be written
     * @throws Filesystem\Exception\ExceptionInterface When a previous extraction cannot be cleared
     * @throws ClientExceptionInterface When an organization search cannot be sent
     * @throws RuntimeException When GitHub refuses an organization search
     * @throws Json\Exception\DecodeException When GitHub's answer is not JSON
     * @throws NativeInvalidArgumentException When a search request cannot be composed
     * @throws Regex\Exception\ExceptionInterface When a tag cannot be matched, which the fixed
     *         pattern makes unreachable
     * @throws Type\Exception\ExceptionInterface When the captured groups are not the shape the
     *         pattern promises, which the fixed pattern makes unreachable
     */
    public function read(mixed $configured, ?Target $fallback = null): array
    {
        if (! is_array($configured)) {
            return $this->defaults();
        }

        $target = $fallback ?? new Target();
        $reader = new RootEntryReader();
        $roots  = [];

        /** @var mixed $entry Deliberately untyped: the entry reader decides what it is */
        foreach ($configured as $entry) {
            // An entry that expands into several roots: a repository's versions, or every
            // repository of an organization that opts in.
            $expanded = new VersionEntryReader()->read($entry, $target)
                ?? new OrganizationEntryReader()->read($entry, $target);

            if (null !== $expanded) {
                array_push($roots, ...$expanded);

                continue;
            }

            $root = $reader->read($entry, $target);

            if (null === $root) {
                continue;
            }

            $roots[] = $root;
        }

        return [] === $roots ? $this->defaults() : $roots;
    }
}
