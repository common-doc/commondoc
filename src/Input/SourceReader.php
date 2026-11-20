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

use Psl\Async;
use Psl\Shell;
use Psl\Str;

use function rtrim;
use function str_replace;

/**
 * Reads a source root's repository identity from the checkout that holds it.
 *
 * The identity is read rather than configured. The tree that was built is the tree
 * a source link should describe, and a declared value could disagree with it in
 * exactly the case nobody would notice: a checkout on a branch other than the one
 * the configuration assumes.
 *
 * A directory that is not a git checkout, or has no `origin`, answers null rather
 * than failing. That is the ordinary case for a `path` root assembled outside a
 * repository, and the caller's own refusal for an unresolvable link is the right
 * answer there, not a build failure.
 *
 * The prefix is what makes a target inside a document meaningful: a link written in
 * `docs/2.0/benchmark-results.md` as `../../benchmarks/` climbs to the repository
 * root, so the URL has to be composed against the repository and not against the
 * source root.
 *
 * @internal
 */
final readonly class SourceReader
{
    private const string SEPARATOR = '/';

    private const string SEPARATORS = '/\\';

    /** The remote a checkout is expected to name its upstream with. */
    private const string ORIGIN = 'origin';

    /**
     * The repository this root came from, or null when it cannot be read.
     *
     * The ref is the commit that was read rather than a branch name. A branch moves,
     * and a documentation link that changed destination after publication would be
     * worse than an ugly URL; a commit names exactly the tree the page was built
     * from. It is also the only answer that stays correct for a detached checkout,
     * where a branch name reports `HEAD`.
     *
     * @param string $directory The root's directory, from `Root::directory()`
     */
    public function read(string $directory): ?Source
    {
        $remote = $this->ask($directory, ['remote', 'get-url', self::ORIGIN]);
        $ref    = $this->ask($directory, ['rev-parse', 'HEAD']);

        if (null === $remote || null === $ref) {
            return null;
        }

        return Source::fromRemote(
            remote: $remote,
            ref   : $ref,
            prefix: $this->prefix($directory),
        );
    }

    /**
     * One git question, or null when it cannot be answered.
     *
     * `Shell\execute` runs the command with its arguments as separate values, so no
     * shell ever parses them and a path containing a space or a quote cannot become
     * part of a command. A non-zero exit is the ordinary "not a repository" answer
     * here, which is why it is caught rather than allowed to fail the build.
     *
     * @param list<string> $arguments
     */
    private function ask(string $directory, array $arguments): ?string
    {
        if ('' === $directory) {
            return null;
        }

        try {
            $output = Shell\execute(
                command         : 'git',
                arguments       : $arguments,
                workingDirectory: $directory,
            );

            $output = Str\trim($output);
        } catch (
            Shell\Exception\ExceptionInterface|Async\Exception\CancelledException|Str\Exception\InvalidArgumentException
        ) {
            return null;
        }

        return '' === $output ? null : $output;
    }

    /**
     * The path from the repository root to this directory.
     *
     * Asked of git rather than assembled from the filesystem, because only the
     * repository knows where its own top level is, and a root nested inside one is
     * the case that matters.
     */
    private function prefix(string $directory): string
    {
        $top = $this->ask($directory, ['rev-parse', '--show-toplevel']);

        if (null === $top) {
            return '';
        }

        $top = rtrim(str_replace(
            search : '\\',
            replace: self::SEPARATOR,
            subject: $top,
        ), characters: self::SEPARATOR);
        $at = rtrim(str_replace(
            search : '\\',
            replace: self::SEPARATOR,
            subject: $directory,
        ), characters: self::SEPARATOR);

        if ($at === $top) {
            return '';
        }

        $prefix = Str\strip_prefix($at, Str\concat($top, self::SEPARATOR));

        if ($prefix === $at) {
            // The directory is not beneath the top level git reported, so no prefix
            // can be trusted and the caller keeps its own refusal.
            return '';
        }

        return $prefix;
    }
}
