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
use CommonDoc\Input\TagTree;
use CommonDocTest\Support\GitCheckout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/**
 * A real repository with a real tag, because the behaviour under test is that a tag's
 * files are read from the object store and not from the working copy.
 */
#[CoversClass(TagTree::class)]
#[CoversMethod(TagTree::class, '__construct')]
#[CoversMethod(TagTree::class, 'extract')]
final class TagTreeTest extends TestCase
{
    private string $directory;

    private string $destination;

    #[Test]
    public function leavesOutASubmoduleBecauseItHoldsNoContent(): void
    {
        $this->repository();

        $sha = (string) new Git()->ask($this->directory, ['rev-parse', 'HEAD']);
        GitCheckout::run(
            $this->directory,
            arguments: 'update-index --add --cacheinfo 160000,%s,docs/vendored',
            values: $sha,
        );
        GitCheckout::run($this->directory, arguments: 'commit --quiet -m submodule');
        GitCheckout::run($this->directory, arguments: 'tag v2.0.0');

        $written = new TagTree()->extract(
            $this->directory,
            ref: 'v2.0.0',
            path: 'docs',
            destination: $this->destination,
        );

        static::assertSame(2, $written);
        static::assertFalse(is_file("{$this->destination}/docs/vendored"));
    }

    #[Test]
    public function leavesOutWhatIsOutsideTheDocumentationDirectory(): void
    {
        $this->repository();

        new TagTree()->extract($this->directory, ref: 'v1.0.0', path: 'docs', destination: $this->destination);

        static::assertFalse(is_file("{$this->destination}/README.md"));
    }

    #[Test]
    public function writesNothingForARefOrPathThatDoesNotExist(): void
    {
        $this->repository();

        static::assertSame(0, new TagTree()->extract(
            $this->directory,
            ref: 'v9.9.9',
            path: 'docs',
            destination: $this->destination,
        ));
        static::assertSame(0, new TagTree()->extract(
            $this->directory,
            ref: 'v1.0.0',
            path: 'absent',
            destination: $this->destination,
        ));
    }

    #[Test]
    public function writesTheFilesAsTheTagHeldThemNotAsTheWorkingCopyHoldsThem(): void
    {
        $this->repository();

        $written = new TagTree()->extract(
            $this->directory,
            ref: 'v1.0.0',
            path: 'docs',
            destination: $this->destination,
        );

        static::assertSame(2, $written);
        static::assertSame("# One\n", file_get_contents("{$this->destination}/docs/index.md"));
        static::assertSame("# Two\n", file_get_contents("{$this->destination}/docs/guide/two.md"));
    }

    protected function setUp(): void
    {
        $base              = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('commondoc-tree-', more_entropy: true);
        $this->directory   = $base . DIRECTORY_SEPARATOR . 'repo';
        $this->destination = $base . DIRECTORY_SEPARATOR . 'out';

        mkdir(
            directory: $this->directory,
            recursive: true,
        );
    }

    protected function tearDown(): void
    {
        GitCheckout::remove(dirname($this->directory));
    }

    private function repository(): void
    {
        GitCheckout::create($this->directory);

        mkdir(
            directory: "{$this->directory}/docs/guide",
            recursive: true,
        );
        file_put_contents("{$this->directory}/docs/index.md", data: "# One\n");
        file_put_contents("{$this->directory}/docs/guide/two.md", data: "# Two\n");

        GitCheckout::run($this->directory, arguments: 'add docs');
        GitCheckout::run($this->directory, arguments: 'commit --quiet -m docs');
        GitCheckout::run($this->directory, arguments: 'tag v1.0.0');

        file_put_contents("{$this->directory}/docs/index.md", data: "# Changed after the tag\n");
        GitCheckout::run($this->directory, arguments: 'commit --quiet -am later');
    }
}
