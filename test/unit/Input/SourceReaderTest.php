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

use CommonDoc\Input\Source;
use CommonDoc\Input\SourceReader;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function chmod;
use function exec;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_writable;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * The identity is read from a real checkout, because the thing being tested is
 * whether the git questions are asked correctly. A stubbed shell would test the
 * stub.
 *
 * The negative case matters as much: a directory that is not a checkout is the
 * ordinary shape of a `path` root, and it must answer null rather than fail the
 * build.
 */
#[CoversClass(SourceReader::class)]
#[CoversMethod(SourceReader::class, 'read')]
final class SourceReaderTest extends TestCase
{
    private string $directory;

    #[Test]
    public function answersNullWhenTheDirectoryIsNotACheckout(): void
    {
        // A path root assembled outside a repository has no source to point at, and
        // that is an ordinary answer rather than a failure.
        static::assertNull(new SourceReader()->read($this->directory));
    }

    #[Test]
    public function readsThePathFromTheRepositoryRootToTheSourceRoot(): void
    {
        // The prefix is what makes a target inside a document meaningful: `../../` from
        // `docs/v1` reaches the repository root only if the prefix is known.
        $repository = $this->repository();
        mkdir(
            directory: "{$repository}/docs/v1",
            recursive: true,
        );
        file_put_contents(
            filename: "{$repository}/docs/v1/index.md",
            data    : "# Index\n",
        );

        $source = new SourceReader()->read("{$repository}/docs/v1");

        static::assertInstanceOf(Source::class, $source);
        static::assertSame('docs/v1', $source->prefix);
    }

    #[Test]
    public function readsTheRepositoryAndTheCommitOfACheckout(): void
    {
        $source = new SourceReader()->read($this->repository());

        static::assertInstanceOf(Source::class, $source);
        static::assertStringStartsWith('https://github.com/example/fixture', $source->url);
        static::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $source->ref);
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('commondoc-source-', more_entropy: true);
        mkdir(
            directory: $this->directory,
            recursive: true,
        );
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    /**
     * One git command in the fixture, which must succeed.
     */
    private function git(string $arguments): void
    {
        $output = [];
        $code   = 0;

        exec(
            command    : sprintf('git -C "%s" %s 2>&1', $this->directory, $arguments),
            output     : $output,
            result_code: $code,
        );

        static::assertSame(0, $code, sprintf('git %s failed: %s', $arguments, implode("\n", $output)));
    }

    /**
     * Removes the fixture, clearing the read-only bit first.
     *
     * Git marks the objects it writes read-only, and Windows refuses to delete a file
     * carrying that bit. The existing tests never met it because they remove build
     * output rather than a repository.
     */
    private function remove(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $path = $entry->getPathname();

            if (! is_writable($path)) {
                chmod(
                    filename   : $path,
                    permissions: 0o777,
                );
            }

            if ($entry->isDir()) {
                rmdir($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }

    /**
     * A repository whose remote points at a GitHub URL, with one commit.
     */
    private function repository(): string
    {
        $repository = $this->directory;

        file_put_contents(
            filename: "{$repository}/README.md",
            data    : "# Fixture\n",
        );

        $this->git('init --quiet');
        $this->git('remote add origin https://github.com/example/fixture.git');
        $this->git('config user.email fixture@example.com');
        $this->git('config user.name Fixture');
        $this->git('add README.md');
        $this->git('commit --quiet -m fixture');

        return $repository;
    }
}
