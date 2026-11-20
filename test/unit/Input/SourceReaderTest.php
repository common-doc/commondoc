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
use CommonDocTest\Support\GitCheckout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chdir;
use function file_put_contents;
use function getcwd;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

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
    public function answersNoPrefixForADirectoryItCannotPlaceBeneathTheTopLevel(): void
    {
        // The reader is handed a path git cannot describe, which is what a relative root
        // looks like from outside the process. Guessing a prefix would point every link
        // at the wrong file, so the refusal is empty and the caller keeps its own.
        $repository = $this->repository();
        $previous   = getcwd();

        static::assertIsString($previous);

        try {
            chdir($repository);

            $source = new SourceReader()->read('.');
        } finally {
            chdir($previous);
        }

        static::assertInstanceOf(Source::class, $source);
        static::assertSame('', $source->prefix);
    }

    #[Test]
    public function answersNullForAnEmptyDirectory(): void
    {
        // `ask()` refuses an empty working directory rather than handing it to the
        // shell, because a shell started in one would answer for wherever the process
        // happens to be running instead of for the root that was asked about.
        static::assertNull(new SourceReader()->read(''));
    }

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
     *
     * @param string ...$values Further sprintf values for the command
     */
    private function git(string $arguments, string ...$values): void
    {
        GitCheckout::run($this->directory, $arguments, ...$values);
    }

    /**
     * Removes the fixture.
     */
    private function remove(string $directory): void
    {
        GitCheckout::remove($directory);
    }

    /**
     * A repository whose remote points at a GitHub URL, with one commit.
     */
    private function repository(): string
    {
        GitCheckout::create($this->directory);

        return $this->directory;
    }
}
