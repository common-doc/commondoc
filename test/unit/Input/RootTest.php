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
use CommonDocTest\Support\AbstractTemporaryTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;

/**
 * A root is a source directory and the prefix it publishes at.
 *
 * The prefix is the whole mechanism that turns several repositories into one
 * path space: once it is applied, a path is relative to the site, so nothing
 * downstream has to know the site was composed.
 */
#[CoversClass(Root::class)]
#[CoversMethod(Root::class, '__construct')]
#[CoversMethod(Root::class, 'directory')]
#[CoversMethod(Root::class, 'landing')]
#[CoversMethod(Root::class, 'mountPath')]
#[CoversMethod(Root::class, 'overlaps')]
final class RootTest extends AbstractTemporaryTree
{
    #[Test]
    public function landsOnItsIndexWhenItHasOne(): void
    {
        $this->makeTree();
        $this->writeFile('docs/index.md');
        $this->writeFile('docs/a-first.md');

        static::assertSame(
            'guide/index.md',
            new Root(
                path : "{$this->tree}/docs",
                mount: '/guide/',
            )->landing(),
        );
    }

    #[Test]
    public function landsOnTheFirstDocumentWhenItHasNoIndex(): void
    {
        $this->makeTree();
        $this->writeFile('docs/installation.md');
        $this->writeFile('docs/configuration.md');
        $this->writeFile('docs/logo.png');

        // Sorted, and a document rather than whatever sorts first among every file.
        static::assertSame(
            'guide/configuration.md',
            new Root(
                path : "{$this->tree}/docs",
                mount: '/guide/',
            )->landing(),
        );
    }

    #[Test]
    public function landsOnTheIndexItWouldHaveWhenItHoldsNoDocumentOrCannotBeRead(): void
    {
        $this->makeTree();
        $this->writeFile('docs/logo.png');

        static::assertSame(
            'guide/index.md',
            new Root(
                path : "{$this->tree}/docs",
                mount: '/guide/',
            )->landing(),
        );
        static::assertSame(
            'guide/index.md',
            new Root(
                path : "{$this->tree}/missing",
                mount: '/guide/',
            )->landing(),
        );
    }

    #[Test]
    public function normalisesAMountWithoutATrailingSlash(): void
    {
        $root = new Root(
            path : 'other',
            mount: '/other',
        );

        static::assertSame('/other/', $root->mount);
        static::assertSame('other/index.md', $root->mountPath('index.md'));
    }

    #[Test]
    public function prefixesAPathWhenMountedBeneathTheSite(): void
    {
        $root = new Root(
            path : '../other',
            mount: '/other/',
        );

        static::assertSame('other/guide/setup.md', $root->mountPath('guide/setup.md'));
    }

    #[Test]
    public function publishesAPathUnchangedWhenMountedAtTheSiteRoot(): void
    {
        $root = new Root(path: 'docs');

        static::assertSame('/', $root->mount);
        static::assertSame('guide/setup.md', $root->mountPath('guide/setup.md'));
    }

    #[Test]
    public function refusesAMountThatCannotPrefixAPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Root(
            path : 'docs',
            mount: 'docs',
        );
    }

    #[Test]
    public function refusesAMountWithMoreThanOneTrailingSlash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Root(
            path : 'docs',
            mount: '/docs//',
        );
    }

    #[Test]
    public function treatsOneMountBeneathAnotherAsOverlapping(): void
    {
        $site  = new Root(path: 'docs');
        $inner = new Root(
            path : 'other',
            mount: '/guide/',
        );

        // A file at `guide/x.md` in the root mounted at `/` and the same path in the
        // root mounted at `/guide/` would publish at one path.
        static::assertTrue($site->overlaps($inner));
        static::assertTrue($inner->overlaps($site));
    }

    #[Test]
    public function treatsTwoRootsAtDistinctMountsAsExclusive(): void
    {
        static::assertFalse(
            new Root(
                path : 'a',
                mount: '/one/',
            )->overlaps(new Root(
                path : 'b',
                mount: '/other/',
            )),
        );
    }

    #[Test]
    public function treatsTwoRootsAtOneMountAsOverlapping(): void
    {
        static::assertTrue(
            new Root(
                path : 'a',
                mount: '/guide/',
            )->overlaps(new Root(
                path : 'b',
                mount: '/guide/',
            )),
        );
    }

    #[Test]
    public function trimsATrailingSeparatorFromTheDirectory(): void
    {
        static::assertSame('docs', new Root(path: 'docs/')->directory());
        static::assertSame('docs', new Root(path: 'docs\\')->directory());
    }

    protected function tearDown(): void
    {
        $this->removeTree();
    }
}
