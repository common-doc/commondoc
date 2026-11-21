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

use CommonDoc\Exception\RuntimeException;
use CommonDoc\Output\SourcePath;

use function explode;
use function implode;
use function rawurlencode;
use function rtrim;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Where a root's documents come from, as the repository that holds them.
 *
 * A document may point outside its own source root, at a directory or a file the
 * site does not publish: a benchmark note links to `benchmarks/`, a contributing
 * guide links to a workflow file. Those are links to the source, and a published
 * page that refused them would make a repository's own documentation
 * unpublishable without editing it.
 *
 * This type is what those links resolve against. It carries the repository URL,
 * the commit that was read, and the path from the repository root to the source
 * root, because a target written inside a document is relative to that root and
 * has to be resolved against the repository to be meaningful.
 *
 * It is read from the checkout rather than configured. The tree that was built is
 * the tree the link should describe, and a declared value could disagree with it.
 *
 * @api
 */
final readonly class Source
{
    private const string SEPARATOR = '/';

    /** The host whose URL shape this type knows. */
    private const string HOST = 'github.com';

    /** The path segment that serves a file at a ref. */
    private const string BLOB = 'blob';

    /** The separator between an owner and a repository in an SSH remote. */
    private const string SCP = ':';

    /**
     * @param string $url    The repository URL, without a trailing `.git` or slash
     * @param string $ref    The git ref the link addresses, normally the commit that was read
     * @param string $prefix The path from the repository root to the source root, such as
     *                       `docs/2.0`, or an empty string when the root is the repository
     */
    public function __construct(
        public string $url,
        public string $ref,
        public string $prefix = '',
    ) {}

    /**
     * The repository URL a git remote names, or null when it is not one this type
     * can build a link for.
     *
     * Both remote spellings are accepted, because a checkout may have been cloned
     * either way and the documentation being built should not depend on which.
     *
     * @param string $remote The output of `git remote get-url origin`
     * @param string $ref    The git ref the link addresses, normally the commit that was read
     * @param string $prefix The path from the repository root to the source root
     *
     * @return self|null Null when the remote is not a GitHub repository this type can build
     *                   a URL for, which leaves the caller's own refusal in place
     */
    public static function fromRemote(string $remote, string $ref, string $prefix = ''): ?self
    {
        $remote = rtrim($remote, characters: '/');

        if (str_starts_with($remote, 'git@')) {
            // git@github.com:owner/repo.git
            $remote =
                'https://'
                . str_replace(
                    search : self::SCP,
                    replace: '/',
                    subject: substr($remote, offset: strlen('git@')),
                );
        }

        if (str_starts_with($remote, 'ssh://git@')) {
            $remote = 'https://' . substr($remote, offset: strlen('ssh://git@'));
        }

        if (! str_starts_with($remote, 'https://')) {
            return null;
        }

        $remote = rtrim($remote, characters: '/');

        if (str_ends_with($remote, '.git')) {
            $remote = substr($remote, offset: 0, length: -4);
        }

        // The host decides whether a `/blob/<ref>/<path>` URL exists at all.
        if (! str_starts_with($remote, 'https://' . self::HOST . '/')) {
            return null;
        }

        return new self(
            url   : $remote,
            ref   : $ref,
            prefix: rtrim($prefix, characters: self::SEPARATOR),
        );
    }

    /**
     * The URL of one file in the repository, encoded so a space or a `#` in a
     * path cannot end the URL early.
     *
     * @param string $path A path from the repository root, with forward slashes
     */
    public function at(string $path): string
    {
        $segments = [];

        foreach (explode(self::SEPARATOR, $path) as $segment) {
            if ('' === $segment) {
                continue;
            }

            $segments[] = rawurlencode($segment);
        }

        return implode(self::SEPARATOR, [
            $this->url,
            self::BLOB,
            rawurlencode($this->ref),
            ...$segments,
        ]);
    }

    /**
     * The repository URL a link inside one document points at, or null when it stays
     * inside this root.
     *
     * A document may link to something its own site does not publish: a benchmark
     * result, a workflow file, a sibling directory. Those targets are not on the site,
     * but they are in the repository, and pointing at it keeps a source tree publishable
     * without editing the links it already had.
     *
     * The target is resolved in repository space rather than in site space, because a
     * site path has already spent its mount and no longer says how many segments it
     * climbed. `$from` is the document's path from the repository root, which the scan
     * recorded while walking, so the climb is counted in the space the link was written
     * for and no mount has to be subtracted back out.
     *
     * Two answers are null rather than a URL, because both leave the caller's own
     * refusal in place:
     *
     * - a target that stays inside this root is a genuinely dead link, not a source link
     * - a target that climbs above the repository names no file anywhere
     *
     * @param string $target The target exactly as the document wrote it
     * @param string $from   The document's path from the repository root
     */
    public function link(string $target, string $from): ?string
    {
        if (str_starts_with($target, self::SEPARATOR)) {
            // An absolute target is relative to the site root, which is the only
            // absolute space a reader knows. Nothing about the repository applies.
            return null;
        }

        try {
            $resolved = SourcePath::resolve($target, $from);
        } catch (RuntimeException) {
            return null;
        }

        if ('' === $this->prefix || str_starts_with($resolved, $this->prefix . self::SEPARATOR)) {
            return null;
        }

        return $this->at($resolved);
    }
}
