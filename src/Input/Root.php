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
use Psl\Filesystem;

use function ltrim;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;

/**
 * One source root, mounted at a prefix inside the site.
 *
 * A site is one published site, but it may be composed from several
 * repositories. Rather than inventing a second kind of build, each repository is
 * a root with a mount prefix, and the prefix is the whole mechanism: a file is
 * published at the prefix followed by its path inside that root. Once the mount
 * is applied, everything downstream sees one path space and one set of paths,
 * exactly as a single-root build did.
 *
 * Mounts must be exclusive. Two roots at `/a/` and `/a/b/` would both claim
 * `/a/b/x.md`, and which one won would depend on iteration order, so
 * `SiteConfig` refuses the combination rather than letting a build publish a file
 * from one repository at a path belonging to another.
 *
 * The directory is deliberately not checked here. Whether a directory exists is
 * a filesystem question, and the walk answers it with the path in the message;
 * this type only states where a root is mounted.
 *
 * @api
 */
final readonly class Root
{
    private const string SEPARATOR = '/';

    private const string SEPARATORS = '/\\';

    /**
     * The names a document has to have to be its directory's index, without the suffix.
     *
     * `readme` first, because GitHub renders a directory's `README.md` and ignores an
     * `index.md`, so that is the name that makes one file the landing page both on GitHub and
     * on the published site. `index` stays accepted because tags already cut carry it.
     */
    private const array DIRECTORY_INDEX = ['readme', 'index'];

    /**
     * The mount prefix, with exactly one trailing slash.
     *
     * `/` for a root that is the site, `/guide/` for one published beneath it.
     *
     * @var non-empty-string A mount always starts with a slash, so it is never empty
     */
    public string $mount;

    /**
     * @param string  $path   The directory holding this root's files
     * @param string  $mount  The prefix it is published at
     * @param ?Source $source The repository the files belong to, when it cannot be read from
     *                        the directory: a tree extracted from a tag is not a checkout
     * @param ?Release $release The component and version the files are, when they are a tag
     *
     * @throws InvalidArgumentException When the mount cannot prefix a path
     */
    public function __construct(
        public string $path,
        string $mount = self::SEPARATOR,
        public ?Source $source = null,
        public ?Release $release = null,
    ) {
        $this->mount = self::normalize($mount);
    }

    /**
     * A mount prefix with exactly one trailing slash.
     *
     * @return non-empty-string A mount always starts with a slash, so it is never empty
     *
     * @throws InvalidArgumentException When the value cannot prefix a path
     */
    private static function normalize(string $mount): string
    {
        if (! str_starts_with($mount, self::SEPARATOR)) {
            throw new InvalidArgumentException(sprintf(
                'The mount "%s" must start with a slash.',
                $mount,
            ));
        }

        if (str_ends_with($mount, '//')) {
            throw new InvalidArgumentException(sprintf(
                'The mount "%s" carries more than one trailing slash.',
                $mount,
            ));
        }

        /** @var non-empty-string $mount A leading slash was required above, so this cannot be empty */
        return str_ends_with($mount, self::SEPARATOR) ? $mount : "{$mount}/";
    }

    /**
     * The directory to walk, without a trailing separator.
     *
     * @return non-empty-string
     */
    public function directory(): string
    {
        $trimmed = rtrim($this->path, characters: self::SEPARATORS);

        return '' === $trimmed ? self::SEPARATOR : $trimmed;
    }

    /**
     * Every document this root holds, as paths relative to it and in sorted order.
     *
     * A root that cannot be read holds none. The scan is what refuses an unreadable root, with
     * the path in the message, and this question is asked before the scan runs.
     *
     * @return list<non-empty-string>
     */
    public function documents(): array
    {
        try {
            return new Markdown()->scan(Tree::at($this->directory())->paths());
        } catch (Filesystem\Exception\ExceptionInterface|InvalidArgumentException) {
            return [];
        }
    }

    /**
     * The document a reader lands on when they are sent to this root, as a site path.
     *
     * Its own directory index when it has one, and otherwise the first document in sorted
     * order, because a link to a page that does not exist is a dead link on a site that is
     * otherwise fine, and most repositories keep their documentation as named pages with no
     * index among them.
     *
     * `README.md` is the index, and `index.md` is accepted after it. That order is not a
     * preference: GitHub renders a directory's `README.md` and ignores an `index.md`
     * entirely, so a repository whose landing page is named `index.md` reads as a file list
     * to anyone browsing it on GitHub. Naming the file for what GitHub renders means one
     * file is the landing page in both places.
     *
     * A root with no document answers with the index it would have.
     *
     * @return non-empty-string
     */
    public function landing(): string
    {
        $documents = $this->documents();

        foreach (self::DIRECTORY_INDEX as $name) {
            foreach ($documents as $document) {
                if (strtolower($document) === $name . Markdown::EXTENSION) {
                    return $this->mountPath($document);
                }
            }
        }

        return $this->mountPath($documents[0] ?? self::DIRECTORY_INDEX[0] . Markdown::EXTENSION);
    }

    /**
     * The path a file inside this root is published at, relative to the site.
     *
     * The mount is stripped of its leading slash and the file's own path is
     * appended, so a root mounted at `/` answers with the path unchanged and a
     * root mounted at `/guide/` answers with `guide/` in front of it.
     *
     * @param non-empty-string $relative A path as the walk produced it
     *
     * @return non-empty-string
     */
    public function mountPath(string $relative): string
    {
        return ltrim($this->mount, characters: self::SEPARATOR) . $relative;
    }

    /**
     * Whether this root and another would publish at overlapping paths.
     *
     * Equality is included because two roots at one mount is the same ambiguity
     * as one mount beneath another: a path would have two owners.
     */
    public function overlaps(self $other): bool
    {
        return (
            str_starts_with($this->mount, $other->mount)
                || str_starts_with($other->mount, $this->mount)
        );
    }
}
