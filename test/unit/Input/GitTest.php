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

use CommonDoc\Input\Git;
use CommonDocTest\Support\GitCheckout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/**
 * Git is asked real questions of a real checkout, because a stubbed shell tests the stub.
 */
#[CoversClass(Git::class)]
#[CoversMethod(Git::class, 'ask')]
#[CoversMethod(Git::class, 'files')]
#[CoversMethod(Git::class, 'show')]
final class GitTest extends TestCase
{
    private string $directory;

    #[Test]
    public function answersNullWhenGitCannotAnswer(): void
    {
        // Not a repository: git exits non-zero, which is the ordinary answer for a `path`
        // root and must not fail the build.
        static::assertNull(new Git()->ask($this->directory, ['rev-parse', 'HEAD']));
    }

    #[Test]
    public function answersNullWhenTheQuestionHasNoOutput(): void
    {
        // A repository with no tags answers `git tag --list` with nothing, which is the
        // absence of an answer and not an answer of the empty string.
        GitCheckout::create($this->directory);

        static::assertNull(new Git()->ask($this->directory, ['tag', '--list']));
    }

    #[Test]
    public function answersWithoutTheTrailingNewlineGitAdds(): void
    {
        GitCheckout::create($this->directory);

        static::assertSame(
            'https://github.com/example/fixture.git',
            new Git()->ask($this->directory, ['remote', 'get-url', 'origin']),
        );
    }

    #[Test]
    public function answersWithTheOutputOfTheQuestion(): void
    {
        GitCheckout::create($this->directory);

        static::assertMatchesRegularExpression(
            '/^[0-9a-f]{40}$/',
            (string) new Git()->ask($this->directory, ['rev-parse', 'HEAD']),
        );
    }

    #[Test]
    public function listsNothingForARefOrPathThatIsNotThere(): void
    {
        $this->commitDocs();

        static::assertSame([], new Git()->files($this->directory, ref: 'HEAD', path: 'absent'));
        static::assertSame([], new Git()->files($this->directory, ref: 'nope', path: 'docs'));
    }

    #[Test]
    public function listsTheFilesARefHoldsBeneathAPath(): void
    {
        $this->commitDocs();

        static::assertSame(
            ['docs/a b.md', 'docs/sub/c.md'],
            new Git()->files($this->directory, ref: 'HEAD', path: 'docs'),
        );
    }

    #[Test]
    public function refusesAnEmptyWorkingDirectoryRatherThanAskingWhereverTheProcessIs(): void
    {
        static::assertNull(new Git()->ask('', ['rev-parse', 'HEAD']));
    }

    #[Test]
    public function showsAFileByteForByteWithItsTrailingNewline(): void
    {
        $this->commitDocs();

        static::assertSame("# C\n", new Git()->show($this->directory, ref: 'HEAD', path: 'docs/sub/c.md'));
    }

    #[Test]
    public function showsNullForAFileTheRefDoesNotHold(): void
    {
        $this->commitDocs();

        static::assertNull(new Git()->show($this->directory, ref: 'HEAD', path: 'docs/missing.md'));
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('commondoc-git-', more_entropy: true);
        mkdir(
            directory: $this->directory,
            recursive: true,
        );
    }

    protected function tearDown(): void
    {
        GitCheckout::remove($this->directory);
    }

    private function commitDocs(): void
    {
        GitCheckout::create($this->directory);

        mkdir(
            directory: "{$this->directory}/docs/sub",
            recursive: true,
        );
        file_put_contents("{$this->directory}/docs/a b.md", data: "# A\n");
        file_put_contents("{$this->directory}/docs/sub/c.md", data: "# C\n");

        GitCheckout::run($this->directory, arguments: 'add docs');
        GitCheckout::run($this->directory, arguments: 'commit --quiet -m docs');
    }
}
