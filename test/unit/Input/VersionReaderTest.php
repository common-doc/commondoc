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
use CommonDoc\Input\VersionReader;
use CommonDocTest\Support\GitCheckout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/**
 * The versions are read from a real checkout with real tags, because the thing being
 * tested is whether git is asked correctly and its answer read correctly. A stubbed shell
 * would test the stub.
 *
 * The negative cases matter as much: a directory that is not a checkout, or has no tags,
 * is the ordinary shape of a `path` root and must answer an empty list rather than fail.
 */
#[CoversClass(VersionReader::class)]
#[CoversMethod(VersionReader::class, '__construct')]
#[CoversMethod(VersionReader::class, 'read')]
final class VersionReaderTest extends TestCase
{
    private string $directory;

    #[Test]
    public function answersNothingForACheckoutWithNoTags(): void
    {
        $this->repository();

        static::assertSame([], new VersionReader()->read($this->directory));
    }

    #[Test]
    public function answersNothingForADirectoryThatIsNotACheckout(): void
    {
        static::assertSame([], new VersionReader()->read($this->directory));
    }

    #[Test]
    public function answersNothingForAnEmptyDirectory(): void
    {
        static::assertSame([], new VersionReader()->read(''));
    }

    #[Test]
    public function readsAPreReleaseLineBecauseThatIsWhatThisFleetHasToday(): void
    {
        $this->repository();

        foreach (['1.0.0-alpha.1', '1.0.0-alpha.9', '1.0.0-alpha.10'] as $tag) {
            $this->tag($tag);
        }

        $versions = new VersionReader()->read($this->directory);

        static::assertCount(1, $versions);
        static::assertSame('1.0.0-alpha.10', $versions[0]->tag);
    }

    #[Test]
    public function readsTheLatestVersionOfEachMajorLineFromTheTags(): void
    {
        $this->repository();

        foreach (['v1.0.0', 'v1.4.2', 'v2.0.0', 'v2.3.1', 'not-a-version'] as $tag) {
            $this->tag($tag);
        }

        static::assertSame(
            ['v1.4.2', 'v2.3.1'],
            array_map(
                static fn(Version $version): string => $version->tag,
                new VersionReader()->read($this->directory),
            ),
        );
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('commondoc-versions-', more_entropy: true);
        mkdir(
            directory: $this->directory,
            recursive: true,
        );
    }

    protected function tearDown(): void
    {
        GitCheckout::remove($this->directory);
    }

    /**
     * A repository with one commit, which is all a tag needs.
     */
    private function repository(): void
    {
        GitCheckout::create($this->directory);
    }

    private function tag(string $name): void
    {
        $arguments = 'tag %s';

        GitCheckout::run($this->directory, $arguments, $name);
    }
}
