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

use CommonDoc\Input\Scan;
use CommonDoc\Input\Target;
use CommonDoc\Input\VersionRoot;
use CommonDocTest\Support\GitCheckout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/**
 * A real repository with real tags: the behaviour under test is that each tag's own
 * files are built, so a stub of git would prove nothing about it.
 */
#[CoversClass(VersionRoot::class)]
#[CoversMethod(VersionRoot::class, '__construct')]
#[CoversMethod(VersionRoot::class, 'of')]
final class VersionRootTest extends TestCase
{
    private string $directory;

    private string $workspace;

    #[Test]
    public function linksEachVersionToItsOwnTag(): void
    {
        $this->repository();

        $roots = new VersionRoot()->of($this->directory, new Target('docs'), $this->workspace);
        $scan  = Scan::of($roots);

        static::assertSame('v1.0.0', $scan->ownerOf('fixture/v1/index.md')?->ref);
        static::assertSame('v2.0.0', $scan->ownerOf('fixture/v2/index.md')?->ref);
        static::assertSame('docs/index.md', $scan->repositoryPathOf('fixture/v1/index.md'));
    }

    #[Test]
    public function mountsEachVersionBesideTheOthersWithItsOwnFiles(): void
    {
        $this->repository();

        $roots = new VersionRoot()->of($this->directory, new Target('docs'), $this->workspace);

        static::assertSame(['/fixture/v1/', '/fixture/v2/'], [$roots[0]->mount, $roots[1]->mount]);
        static::assertSame("# One\n", file_get_contents("{$roots[0]->path}/index.md"));
        static::assertSame("# Two\n", file_get_contents("{$roots[1]->path}/index.md"));
    }

    #[Test]
    public function readsADottedMajorDirectoryWhenTheTargetStatesOne(): void
    {
        GitCheckout::create($this->directory);
        mkdir(
            directory: "{$this->directory}/docs/2.0",
            recursive: true,
        );
        file_put_contents("{$this->directory}/docs/2.0/index.md", data: "# Two\n");
        GitCheckout::run($this->directory, arguments: 'add docs');
        GitCheckout::run($this->directory, arguments: 'commit --quiet -m two');
        GitCheckout::run($this->directory, arguments: 'tag v2.0.0');

        $roots = new VersionRoot()->of($this->directory, new Target('docs/{major}.0'), $this->workspace);

        static::assertCount(1, $roots);
        static::assertSame('docs/2.0', $roots[0]->source?->prefix);
    }

    #[Test]
    public function readsEachTagFromItsOwnMajorDirectoryWhenTheTargetNamesOne(): void
    {
        GitCheckout::create($this->directory);

        foreach ([1 => 'One', 2 => 'Two'] as $major => $title) {
            mkdir(
                directory: "{$this->directory}/docs/v{$major}",
                recursive: true,
            );
            file_put_contents("{$this->directory}/docs/v{$major}/index.md", data: "# {$title}\n");
            GitCheckout::run($this->directory, arguments: 'add docs');
            GitCheckout::run($this->directory, arguments: 'commit --quiet -m %s', values: "v{$major}");
            GitCheckout::run($this->directory, arguments: 'tag v%d.0.0', values: (string) $major);
        }

        $roots = new VersionRoot()->of($this->directory, new Target('docs/v{major}'), $this->workspace);

        static::assertCount(2, $roots);
        static::assertSame("# One\n", file_get_contents("{$roots[0]->path}/index.md"));
        static::assertSame("# Two\n", file_get_contents("{$roots[1]->path}/index.md"));
        static::assertSame('docs/v2', $roots[1]->source?->prefix);
    }

    #[Test]
    public function skipsATagThatPredatesTheDocumentation(): void
    {
        $this->repository();

        // v0.9.0 was tagged before docs existed, so it has no root and costs nothing.
        static::assertCount(2, new VersionRoot()->of($this->directory, new Target('docs'), $this->workspace));
    }

    #[Test]
    public function yieldsNothingForADirectoryThatIsNotARepository(): void
    {
        static::assertSame([], new VersionRoot()->of($this->directory, new Target('docs'), $this->workspace));
    }

    protected function setUp(): void
    {
        $base            = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('commondoc-version-', more_entropy: true);
        $this->directory = "{$base}/fixture";
        $this->workspace = "{$base}/work";

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
        GitCheckout::run($this->directory, arguments: 'tag v0.9.0');

        mkdir(
            directory: "{$this->directory}/docs",
            recursive: true,
        );
        file_put_contents("{$this->directory}/docs/index.md", data: "# One\n");
        GitCheckout::run($this->directory, arguments: 'add docs');
        GitCheckout::run($this->directory, arguments: 'commit --quiet -m one');
        GitCheckout::run($this->directory, arguments: 'tag v1.0.0');

        file_put_contents("{$this->directory}/docs/index.md", data: "# Two\n");
        GitCheckout::run($this->directory, arguments: 'commit --quiet -am two');
        GitCheckout::run($this->directory, arguments: 'tag v2.0.0');
    }
}
