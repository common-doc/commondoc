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

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Psl\Regex;
use Psl\Type;
use UnexpectedValueException;

use function array_values;
use function ksort;

/**
 * One released version of a repository, read from a git tag.
 *
 * A tag is a version only when it is a complete semantic version: three numbers, an
 * optional pre-release and optional build metadata, with an optional leading `v`. Anything
 * else a repository tags, a date or a name or a partial number, is not a version and is
 * not guessed at, because a wrong major would publish a documentation set under the
 * wrong line.
 *
 * Ordering is not reimplemented. `composer/semver` already knows that `1.0.0-alpha.10`
 * follows `1.0.0-alpha.9` and that a release follows every pre-release of itself, so this
 * type holds the normalized form it compares with and asks it.
 *
 * @api
 */
final readonly class Version
{
    /** A complete semantic version, with the major captured. */
    private const string PATTERN = '~^v?(\d+)\.\d+\.\d+(?:-[0-9A-Za-z.\-]+)?(?:\+[0-9A-Za-z.\-]+)?$~';

    /** The stability `composer/semver` reports for a release rather than a pre-release. */
    private const string STABLE = 'stable';

    /**
     * @param string $tag        The tag exactly as the repository wrote it
     * @param int    $major      The major version, which names the line this belongs to
     * @param string $normalized The form `composer/semver` orders by
     * @param bool   $stable     Whether this is a release rather than a pre-release
     */
    private function __construct(
        public string $tag,
        public int $major,
        public string $normalized,
        public bool $stable,
    ) {}

    /**
     * The version a tag names, or null when the tag is not a complete semantic version.
     *
     * @throws Regex\Exception\ExceptionInterface When the pattern cannot be matched, which
     *         the fixed pattern makes unreachable
     * @throws Type\Exception\ExceptionInterface When the captured groups are not the shape
     *         the pattern promises, which the fixed pattern makes unreachable
     */
    public static function fromTag(string $tag): ?self
    {
        $captured = Regex\first_match(
            subject      : $tag,
            pattern      : self::PATTERN,
            captureGroups: Type\shape([1 => Type\string()], allowUnknownFields: true),
        );

        if (null === $captured) {
            return null;
        }

        try {
            $normalized = new VersionParser()->normalize($tag);
        } catch (UnexpectedValueException) {
            return null;
        }

        return new self(
            tag       : $tag,
            major     : (int) $captured[1],
            normalized: $normalized,
            stable    : self::STABLE === VersionParser::parseStability($tag),
        );
    }

    /**
     * The latest version of each major line among some tags, in ascending order of major.
     *
     * A repository that has tagged `1.0.0`, `1.4.2`, `2.0.0` and `2.3.1` has two lines, and a
     * site documents each by its newest version, so a reader on the v1 pages reads about
     * `1.4.2` and a reader on the v2 pages reads about `2.3.1`. Every other tag in a line is
     * superseded and is not published.
     *
     * Tags that are not complete semantic versions are ignored rather than refused: a
     * repository tags what it likes, and one stray tag must not cost a site its versions.
     *
     * @param list<string> $tags Every tag a repository has, in any order
     *
     * @return list<self>
     *
     * @throws Regex\Exception\ExceptionInterface When a tag cannot be matched, which the fixed
     *         pattern makes unreachable
     * @throws Type\Exception\ExceptionInterface When the captured groups are not the shape the
     *         pattern promises, which the fixed pattern makes unreachable
     */
    public static function latestOfEachMajor(array $tags): array
    {
        /** @var array<int, self> $latest */
        $latest = [];

        foreach ($tags as $tag) {
            $version = self::fromTag($tag);

            if (null === $version) {
                continue;
            }

            $current = $latest[$version->major] ?? null;

            if (null === $current || $version->supersedes($current)) {
                $latest[$version->major] = $version;
            }
        }

        ksort($latest);

        return array_values($latest);
    }

    /**
     * Whether this version is the better answer to "the latest of its line" than another.
     *
     * A release outranks any pre-release, whatever their numbers, because a line that has
     * shipped a release is documented by it and not by a later candidate. Between two of
     * the same stability the higher version wins.
     */
    public function supersedes(self $other): bool
    {
        if ($this->stable !== $other->stable) {
            return $this->stable;
        }

        return Comparator::greaterThan($this->normalized, $other->normalized);
    }
}
