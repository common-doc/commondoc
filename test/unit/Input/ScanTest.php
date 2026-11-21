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

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use CommonDoc\Input\Scan;
use CommonDocTest\Support\GitCheckout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

use const DIRECTORY_SEPARATOR;

/**
 * What the scan phase found, and where each file is.
 *
 * The mapping is the only place a site path and a disk path are related, so it is
 * where a document being read from the wrong file would show up.
 */
#[CoversClass(Scan::class)]
#[CoversMethod(Scan::class, 'of')]
#[CoversMethod(Scan::class, 'ownerOf')]
#[CoversMethod(Scan::class, 'repositoryPathOf')]
#[CoversMethod(Scan::class, 'sourceOf')]
final class ScanTest extends TestCase
{
    #[Test]
    public function findsNothingInAnEmptyRoot(): void
    {
        $tree = $this->temporaryTree();

        try {
            $scan = Scan::of([new Root(path: $tree)]);

            static::assertSame([], $scan->sources);
            static::assertSame([], $scan->documents);
            static::assertSame([], $scan->assets);
        } finally {
            $this->removeDirectory($tree);
        }
    }

    #[Test]
    public function namesEveryPathByItsSitePathRatherThanItsRepository(): void
    {
        $tree = $this->temporaryTree();

        try {
            mkdir("{$tree}/one", recursive: true);
            mkdir("{$tree}/two", recursive: true);

            file_put_contents(
                filename: "{$tree}/one/index.md",
                data    : "# One\n",
            );
            file_put_contents(
                filename: "{$tree}/two/guide.md",
                data    : "# Guide\n",
            );

            $scan = Scan::of([
                new Root(
                    path : "{$tree}/one",
                    mount: '/one/',
                ),
                new Root(
                    path : "{$tree}/two",
                    mount: '/two/',
                ),
            ]);

            // Sorted rather than in configuration order, so a report diffs.
            static::assertSame(['one/index.md', 'two/guide.md'], $scan->documents);
            static::assertSame("{$tree}/two/guide.md", $scan->sourceOf('two/guide.md'));
        } finally {
            $this->removeDirectory($tree);
        }
    }

    #[Test]
    public function partitionsOneRootIntoDocumentsAndAssets(): void
    {
        $tree = $this->temporaryTree();

        try {
            file_put_contents(
                filename: "{$tree}/index.md",
                data    : "# Home\n",
            );
            file_put_contents(
                filename: "{$tree}/site.css",
                data    : 'body{}',
            );

            $scan = Scan::of([new Root(path: $tree)]);

            static::assertSame(['index.md'], $scan->documents);
            static::assertSame(['site.css'], $scan->assets);
            static::assertSame(
                ['index.md' => "{$tree}/index.md", 'site.css' => "{$tree}/site.css"],
                $scan->sources,
            );
        } finally {
            $this->removeDirectory($tree);
        }
    }

    #[Test]
    public function recordsTheRepositoryAndTheRepositoryPathOfEveryFile(): void
    {
        // Both maps are what a link that leaves the site is resolved with, and the scan is
        // the only place they are produced. A root walked without its repository being
        // asked would silently cost every source link in the build, and a missing
        // repository path would leave the rewriter with nothing to resolve the climb
        // against. They are recorded together here so neither can be dropped alone.
        $tree = $this->temporaryTree();

        try {
            GitCheckout::create($tree);

            file_put_contents(
                filename: "{$tree}/index.md",
                data    : "# Index\n",
            );

            $scan = Scan::of([new Root(
                path : $tree,
                mount: '/docs/',
            )]);

            static::assertSame('https://github.com/example/fixture', $scan->ownerOf('docs/index.md')?->url);
            // The prefix is empty because the root is the repository, so the path from the
            // repository root is the file's own path inside that root.
            static::assertSame('index.md', $scan->repositoryPathOf('docs/index.md'));
        } finally {
            $this->removeDirectory($tree);
        }
    }

    #[Test]
    public function refusesAPathTheScanDidNotFind(): void
    {
        // A miss is a defect in this package, not something a deployment can cause.
        // Answering with null would let an unreadable path reach the reader and be
        // reported against a document that is fine.
        $this->expectException(InvalidArgumentException::class);

        new Scan(
            sources  : [],
            documents: [],
            assets   : [],
        )->sourceOf('missing.md');
    }

    #[Test]
    public function sortsWhatItFoundRatherThanFollowingConfigurationOrder(): void
    {
        $tree = $this->temporaryTree();

        try {
            mkdir("{$tree}/a", recursive: true);
            mkdir("{$tree}/b", recursive: true);

            file_put_contents(
                filename: "{$tree}/a/index.md",
                data    : "# A\n",
            );
            file_put_contents(
                filename: "{$tree}/b/index.md",
                data    : "# B\n",
            );

            // Declared in reverse, so a scan that inherited configuration order would
            // produce the opposite list and a report would reorder itself between
            // runs.
            $scan = Scan::of([
                new Root(
                    path : "{$tree}/b",
                    mount: '/b/',
                ),
                new Root(
                    path : "{$tree}/a",
                    mount: '/a/',
                ),
            ]);

            static::assertSame(['a/index.md', 'b/index.md'], $scan->documents);
            static::assertSame(['a/index.md', 'b/index.md'], array_keys($scan->sources));
        } finally {
            $this->removeDirectory($tree);
        }
    }

    private function removeDirectory(string $directory): void
    {
        GitCheckout::remove($directory);
    }

    private function temporaryTree(): string
    {
        $tree = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'commondoc-scan-' . bin2hex(random_bytes(8));

        mkdir($tree, recursive: true);

        return $tree;
    }
}
