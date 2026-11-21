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

namespace CommonDocTest\Container;

use CommonDoc\Container\RootEntryReader;
use CommonDoc\Container\RootReader;
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use CommonDoc\Input\Target;
use CommonDocTest\Support\GitCheckout;
use DirectoryIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function str_replace;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Reading a list is not the same job as reading a scalar.
 *
 * An entry has a shape and a mount that the type validates, and a fallback that must
 * not discard the entries that were right. A whole-list fallback would cost every
 * root because one was mistyped, which is the failure a deployment cannot see.
 */
#[CoversClass(RootReader::class)]
#[CoversClass(RootEntryReader::class)]
#[CoversMethod(RootReader::class, 'defaults')]
#[CoversMethod(RootReader::class, 'read')]
#[CoversMethod(RootEntryReader::class, 'read')]
final class RootReaderTest extends TestCase
{
    private static function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }

            return;
        }

        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            self::removeTree($entry->getPathname());
        }

        rmdir($path);
    }

    #[Test]
    public function dropsOnlyTheEntriesThatCannotNameADirectory(): void
    {
        $roots = new RootReader()->read([
            ['mount' => '/broken/'],
            ['path' => ''],
            'not an entry',
            ['path' => 'other', 'mount' => 42],
            // The valid entry comes last and is not the default, on purpose: a
            // reader that stopped at the first bad entry would fall back to the
            // default and look correct.
            ['path' => 'docs/other'],
        ]);

        static::assertCount(1, $roots);
        static::assertSame('docs/other', $roots[0]->path);
    }

    #[Test]
    public function expandsARepositoryThatAsksForItsVersionsIntoARootPerVersion(): void
    {
        $repository = $this->temporaryTree([]);

        try {
            GitCheckout::create($repository);
            mkdir(
                directory: "{$repository}/docs/v1",
                recursive: true,
            );
            file_put_contents("{$repository}/docs/v1/index.md", data: "# One\n");
            GitCheckout::run($repository, arguments: 'add docs');
            GitCheckout::run($repository, arguments: 'commit --quiet -m docs');
            GitCheckout::run($repository, arguments: 'tag v1.0.0');
            GitCheckout::run($repository, arguments: 'tag v2.0.0');

            $roots = new RootReader()->read([['repository' => $repository, 'versions' => true]]);
        } finally {
            GitCheckout::remove($repository);
        }

        static::assertCount(2, $roots);
        static::assertSame(['/fixture/v1/', '/fixture/v2/'], [$roots[0]->mount, $roots[1]->mount]);
        static::assertNotNull($roots[0]->release);
    }

    #[Test]
    public function fallsBackToTheDefaultsWhenNothingWasConfigured(): void
    {
        $defaults = new RootReader()->defaults();

        foreach ([null, 'not a list', []] as $configured) {
            $roots = new RootReader()->read($configured);

            static::assertEquals($defaults, $roots);
            static::assertSame('docs/v1', $roots[0]->path);
            static::assertSame('/', $roots[0]->mount);
        }
    }

    #[Test]
    public function letsAnEntryOverrideTheSiteTarget(): void
    {
        $root = $this->temporaryTree(['docs/book/index.md' => '# home']);

        try {
            // The one repository that kept its DocBook layout states its own target.
            $roots = new RootReader()->read(
                configured: [['repository' => $root, 'mount' => '/', 'target' => 'docs/book']],
                fallback  : new Target('docs/v1'),
            );

            static::assertSame("{$root}/docs/book", $roots[0]->path);
        } finally {
            self::removeTree($root);
        }
    }

    #[Test]
    public function publishesAtTheSiteRootWhenAnEntryNamesNoMount(): void
    {
        $roots = new RootReader()->read([['path' => 'docs/v1']]);

        static::assertCount(1, $roots);
        static::assertSame('/', $roots[0]->mount);
    }

    #[Test]
    public function readsAnEntryAsPlainWhenItAsksForVersionsWithoutNamingARepository(): void
    {
        $roots = new RootReader()->read([['path' => 'docs', 'versions' => true]]);

        static::assertCount(1, $roots);
        static::assertNull($roots[0]->release);
        static::assertSame('docs', $roots[0]->path);
    }

    #[Test]
    public function readsEveryEntryThatCanNameARoot(): void
    {
        $roots = new RootReader()->read([
            ['path' => 'docs/v1', 'mount' => '/commondoc/'],
            ['path' => '../other', 'mount' => '/other/'],
        ]);

        static::assertCount(2, $roots);
        static::assertSame('docs/v1', $roots[0]->path);
        static::assertSame('/commondoc/', $roots[0]->mount);
        static::assertSame('../other', $roots[1]->path);
    }

    #[Test]
    public function refusesAMountThatCannotPrefixAPath(): void
    {
        // A mount is not a typo in the same sense as a missing path: publishing at
        // a path the operator did not write is worse than refusing to start.
        $this->expectException(InvalidArgumentException::class);

        new RootReader()->read([['path' => 'docs', 'mount' => 'docs']]);
    }

    #[Test]
    public function refusesARepositoryWhoseTargetHoldsNoDocumentation(): void
    {
        $root = $this->temporaryTree(['docs/book/index.md' => '# home']);

        try {
            // Composing it would build cleanly and publish nothing, which is
            // indistinguishable from a repository with no pages.
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('is not a readable directory');

            new RootReader()->read(
                configured: [['repository' => $root, 'mount' => '/']],
                fallback  : new Target('docs/v1'),
            );
        } finally {
            self::removeTree($root);
        }
    }

    #[Test]
    public function resolvesARepositoryEntryThroughTheSiteTarget(): void
    {
        $root = $this->temporaryTree(['docs/v1/index.md' => '# home']);

        try {
            $roots = new RootReader()->read(
                configured: [['repository' => $root, 'mount' => '/']],
                fallback  : new Target('docs/v1'),
            );

            // The entry names a repository, not a directory: the target decides where inside.
            static::assertCount(1, $roots);
            static::assertSame("{$root}/docs/v1", $roots[0]->path);
        } finally {
            self::removeTree($root);
        }
    }

    #[Test]
    public function returnsRootsTheSiteCanAccept(): void
    {
        $roots = new RootReader()->read([
            ['path' => 'a', 'mount' => '/one/'],
            ['path' => 'b', 'mount' => '/two/'],
        ]);

        static::assertInstanceOf(Root::class, $roots[0]);
        static::assertNotSame($roots[0]->mount, $roots[1]->mount);
    }

    /**
     * @param array<string, string> $files Path relative to the root, then its contents
     */
    private function temporaryTree(array $files): string
    {
        $root = (string) tempnam(
            directory: sys_get_temp_dir(),
            prefix   : 'commondoc-roots-',
        );
        unlink($root);
        mkdir(directory: $root);

        foreach ($files as $path => $contents) {
            $directory = dirname("{$root}/{$path}");

            if (! is_dir($directory)) {
                mkdir(
                    directory: $directory,
                    recursive: true,
                );
            }

            file_put_contents("{$root}/{$path}", $contents);
        }

        return str_replace(
            search : '\\',
            replace: '/',
            subject: $root,
        );
    }
}
