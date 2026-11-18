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

use CommonDoc\Input\Asset;
use CommonDocTest\Support\AbstractTemporaryTree;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Asset::class)]
#[CoversMethod(Asset::class, 'scan')]
final class AssetTest extends AbstractTemporaryTree
{
    #[Test]
    public function excludesMarkdownRegardlessOfCase(): void
    {
        $this->makeTree();
        $this->writeFile('a.md', contents: '# A');
        $this->writeFile('b.MD', contents: '# B');
        $this->writeFile('keep.txt', contents: 'keep');

        static::assertSame(['keep.txt'], new Asset()->scan($this->tree));
    }

    #[Test]
    public function findsFilesThatAreNotMarkdown(): void
    {
        $this->makeTree();
        $this->writeFile('index.md', contents: '# Home');
        $this->writeFile('images/logo.png', contents: 'png');
        $this->writeFile('style.css', contents: 'body{}');

        static::assertSame(
            ['images/logo.png', 'style.css'],
            new Asset()->scan($this->tree),
        );
    }

    #[Test]
    public function ignoresHiddenFilesAndDirectories(): void
    {
        $this->makeTree();
        $this->writeFile('.env', contents: 'secret');
        $this->writeFile('.git/hooks/pre-commit', contents: '#!/bin/sh');
        $this->writeFile('logo.svg', contents: '<svg/>');

        static::assertSame(['logo.svg'], new Asset()->scan($this->tree));
    }

    #[Test]
    public function refusesARootThatIsNotADirectory(): void
    {
        $this->makeTree();
        $this->writeFile('logo.png', contents: 'png');

        $this->expectException(InvalidArgumentException::class);

        new Asset()->scan("{$this->tree}/logo.png");
    }

    #[Test]
    public function reportsAnEmptyFileBecauseItIsReadable(): void
    {
        $this->makeTree();
        $this->writeFile('empty.txt', contents: '');

        static::assertSame(['empty.txt'], new Asset()->scan($this->tree));
    }

    #[Test]
    public function returnsNothingForAnEmptyRoot(): void
    {
        $this->makeTree();

        static::assertSame([], new Asset()->scan($this->tree));
    }

    protected function tearDown(): void
    {
        $this->removeTree();
    }
}
