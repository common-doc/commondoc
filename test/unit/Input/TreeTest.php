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

use CommonDoc\Input\Tree;
use CommonDocTest\Support\AbstractTemporaryTree;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\Attributes\Test;

use function chmod;

#[CoversClass(Tree::class)]
#[CoversMethod(Tree::class, 'at')]
#[CoversMethod(Tree::class, 'paths')]
final class TreeTest extends AbstractTemporaryTree
{
    #[Test]
    public function listsEveryFileRelativeToTheRootAndOrdered(): void
    {
        $this->makeTree();
        $this->writeFile('b.md', contents: '# B');
        $this->writeFile('a/z.txt', contents: 'z');
        $this->writeFile('a/y.md', contents: '# Y');

        static::assertSame(
            ['a/y.md', 'a/z.txt', 'b.md'],
            Tree::at($this->tree)->paths(),
        );
    }

    #[Test]
    public function neverDescendsIntoAHiddenDirectory(): void
    {
        $this->makeTree();
        $this->writeFile('.git/config', contents: 'x');
        $this->writeFile('.github/pull-request.md', contents: '# PR');
        $this->writeFile('guide.md', contents: '# G');

        static::assertSame(['guide.md'], Tree::at($this->tree)->paths());
    }

    #[Test]
    public function ordersByByteValueRatherThanByFilesystemOrder(): void
    {
        $this->makeTree();
        $this->writeFile('a.md', contents: '# a');
        $this->writeFile('B.md', contents: '# B');

        // Uppercase sorts before lowercase by byte value, which is the opposite
        // of the case-insensitive order most platforms return entries in. A
        // filesystem that happens to list sorted cannot mask a missing sort.
        static::assertSame(['B.md', 'a.md'], Tree::at($this->tree)->paths());
    }

    #[Test]
    public function refusesAnEmptyRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a directory');

        Tree::at('');
    }

    /**
     * Windows has no mode bit to clear, so the refusal cannot be provoked there.
     */
    #[Test]
    #[RequiresOperatingSystemFamily('Linux')]
    public function refusesARootThatCannotBeRead(): void
    {
        $this->makeTree();
        chmod($this->tree, permissions: 0o000);

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('is not readable');

            Tree::at($this->tree);
        } finally {
            chmod($this->tree, permissions: 0o755);
        }
    }

    #[Test]
    public function refusesARootThatDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Tree::at('/commondoc-does-not-exist-anywhere');
    }

    #[Test]
    public function refusesARootThatIsNotADirectory(): void
    {
        $this->makeTree();
        $this->writeFile('file.md', contents: '# F');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a directory');

        Tree::at("{$this->tree}/file.md");
    }

    #[Test]
    public function returnsNothingForAnEmptyRoot(): void
    {
        $this->makeTree();

        static::assertSame([], Tree::at($this->tree)->paths());
    }

    #[Test]
    public function skipsHiddenFiles(): void
    {
        $this->makeTree();
        $this->writeFile('.env', contents: 'secret');
        $this->writeFile('visible.md', contents: '# V');

        static::assertSame(['visible.md'], Tree::at($this->tree)->paths());
    }

    #[Test]
    public function usesForwardSlashesForNestedPaths(): void
    {
        $this->makeTree();
        $this->writeFile('one/two/three.md', contents: '# T');

        static::assertSame(['one/two/three.md'], Tree::at($this->tree)->paths());
    }

    protected function tearDown(): void
    {
        $this->removeTree();
    }
}
