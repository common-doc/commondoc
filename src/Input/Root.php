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

use function ltrim;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_starts_with;

/**
 * One source root, mounted at a prefix inside the site.
 *
 * A site is one published site, but it may be composed from several
 * repositories. Rather than inventing a second kind of build, each repository is
 * a root with a mount prefix, and the prefix is the whole mechanism: a file is
 * published at the prefix followed by its path inside that root. Once the mount
 * is applied, everything downstream sees one address space and one set of paths,
 * exactly as a single-root build did.
 *
 * Mounts must be exclusive. Two roots at `/a/` and `/a/b/` would both claim
 * `/a/b/x.md`, and which one won would depend on iteration order, so
 * `SiteConfig` refuses the combination rather than letting a build publish a file
 * from one repository at an address belonging to another.
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
     * The mount prefix, with exactly one trailing slash.
     *
     * `/` for a root that is the site, `/guide/` for one published beneath it.
     */
    public string $mount;

    /**
     * @param string $path  The directory holding this root's files
     * @param string $mount The prefix it is published at
     *
     * @throws InvalidArgumentException When the mount cannot prefix an address
     */
    public function __construct(
        public string $path,
        string $mount = self::SEPARATOR,
    ) {
        $this->mount = self::normalize($mount);
    }

    /**
     * A mount prefix with exactly one trailing slash.
     *
     * @throws InvalidArgumentException When the value cannot prefix an address
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
     * Whether this root and another would publish at overlapping addresses.
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
