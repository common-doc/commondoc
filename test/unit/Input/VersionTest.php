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

namespace CommonDocTest\Input;

use CommonDoc\Input\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * A version is a complete semantic version read from a tag, and a line is the latest of
 * one major.
 *
 * Ordering is `composer/semver`'s, so these tests pin the decisions this type makes on top
 * of it: what counts as a version, and which of two versions in one line wins.
 */
#[CoversClass(Version::class)]
#[CoversMethod(Version::class, 'fromTag')]
#[CoversMethod(Version::class, 'latestOfEachMajor')]
#[CoversMethod(Version::class, 'supersedes')]
final class VersionTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function tagsThatAreNotVersions(): array
    {
        return [
            'a partial number' => ['2.0'],
            'a bare major'     => ['2'],
            'a name'           => ['release-3'],
            'a date'           => ['20240101'],
            'a branch'         => ['dev-main'],
            'a prefixed name'  => ['rc1'],
            'an empty tag'     => [''],
            'trailing text'    => ['1.0.0 final'],
            'a leading name'   => ['version-1.0.0'],
        ];
    }

    /**
     * @param list<Version> $versions
     *
     * @return list<string>
     */
    private static function tagsOf(array $versions): array
    {
        return array_map(
            static fn(Version $version): string => $version->tag,
            $versions,
        );
    }

    #[Test]
    public function answersNothingForNoTagsOrNoVersions(): void
    {
        static::assertSame([], Version::latestOfEachMajor([]));
        static::assertSame([], Version::latestOfEachMajor(['stray', 'release-3']));
    }

    #[Test]
    public function aReleaseSupersedesAPreReleaseWhateverTheirNumbers(): void
    {
        // A line that has shipped a release is documented by it, not by a later candidate.
        $release   = Version::fromTag('2.0.1');
        $candidate = Version::fromTag('2.1.0-rc.1');

        static::assertNotNull($release);
        static::assertNotNull($candidate);
        static::assertTrue($release->supersedes($candidate));
        static::assertFalse($candidate->supersedes($release));
    }

    #[Test]
    #[DataProvider('tagsThatAreNotVersions')]
    public function ignoresATagThatIsNotACompleteSemanticVersion(string $tag): void
    {
        static::assertNull(Version::fromTag($tag));
    }

    #[Test]
    public function keepsTheTagExactlyAsTheRepositoryWroteIt(): void
    {
        static::assertSame('v2.3.1', Version::fromTag('v2.3.1')?->tag);
    }

    #[Test]
    public function ordersPreReleasesNumericallyRatherThanAsText(): void
    {
        // As text `alpha.10` sorts before `alpha.9`, which would publish an older
        // pre-release as the latest of a line.
        $newer = Version::fromTag('1.0.0-alpha.10');
        $older = Version::fromTag('1.0.0-alpha.9');

        static::assertNotNull($newer);
        static::assertNotNull($older);
        static::assertTrue($newer->supersedes($older));
        static::assertFalse($older->supersedes($newer));
    }

    #[Test]
    public function picksTheLatestOfEachMajorLineInAscendingOrder(): void
    {
        // Out of order on purpose, with a stray tag FIRST: a reader that stopped at the
        // first tag that is not a version would answer nothing, and one that took the last
        // tag of a line would answer differently.
        $tags = ['stray', 'v2.0.0', 'v1.4.2', 'v2.3.1', 'v1.0.0', 'also-stray', 'v2.3.0'];

        static::assertSame(
            ['v1.4.2', 'v2.3.1'],
            self::tagsOf(Version::latestOfEachMajor($tags)),
        );
    }

    #[Test]
    public function readsTheMajorFromAnyCompleteSemanticVersion(): void
    {
        static::assertSame(1, Version::fromTag('1.0.0-alpha.9')?->major);
        static::assertSame(2, Version::fromTag('v2.3.1')?->major);
        static::assertSame(10, Version::fromTag('v10.0.0')?->major);
        static::assertSame(1, Version::fromTag('1.0.0+build.5')?->major);
    }

    #[Test]
    public function tellsAReleaseFromAPreRelease(): void
    {
        static::assertTrue(Version::fromTag('2.3.1')?->stable);
        static::assertTrue(Version::fromTag('2.3.1+build.5')?->stable);
        static::assertFalse(Version::fromTag('2.0.0-rc.1')?->stable);
        static::assertFalse(Version::fromTag('1.0.0-alpha.9')?->stable);
    }

    #[Test]
    public function usesAPreReleaseOnlyForALineThatHasNoRelease(): void
    {
        // Every line this fleet has today is a pre-release line, so a line with no release
        // must still be represented, by its newest pre-release.
        static::assertSame(
            ['1.0.0-alpha.10', '2.0.1', '3.0.0-rc.1'],
            self::tagsOf(Version::latestOfEachMajor([
                '1.0.0-alpha.1',
                '1.0.0-alpha.9',
                '1.0.0-alpha.10',
                '2.0.0',
                '2.1.0-rc.1',
                '2.0.1',
                '3.0.0-beta.2',
                '3.0.0-rc.1',
            ])),
        );
    }
}
