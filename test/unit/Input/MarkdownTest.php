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

use CommonDoc\Input\Markdown;
use CommonDocTest\Support\AbstractTemporaryTree;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Markdown::class)]
#[CoversMethod(Markdown::class, 'scan')]
final class MarkdownTest extends AbstractTemporaryTree
{
    #[Test]
    public function excludesEverythingThatIsNotMarkdown(): void
    {
        $this->makeTree();
        $this->writeFile('index.md', contents: '# Home');
        $this->writeFile('style.css', contents: 'body{}');
        $this->writeFile('notes.txt', contents: 'notes');
        $this->writeFile('image.png', contents: 'png');

        static::assertSame(['index.md'], new Markdown()->scan($this->tree));
    }

    #[Test]
    public function findsDocumentsAtTheRootAndNested(): void
    {
        $this->makeTree();
        $this->writeFile('index.md', contents: '# Home');
        $this->writeFile('guide/setup.md', contents: '# Setup');

        static::assertSame(
            ['guide/setup.md', 'index.md'],
            new Markdown()->scan($this->tree),
        );
    }

    #[Test]
    public function ignoresHiddenDocumentsAndDirectories(): void
    {
        $this->makeTree();
        $this->writeFile('.hidden.md', contents: '# Hidden');
        $this->writeFile('.github/notes.md', contents: '# Notes');
        $this->writeFile('visible.md', contents: '# Visible');

        static::assertSame(['visible.md'], new Markdown()->scan($this->tree));
    }

    #[Test]
    public function matchesTheExtensionWithoutRegardToCase(): void
    {
        $this->makeTree();
        $this->writeFile('upper/MD.MD', contents: '# Upper');
        $this->writeFile('mixed/Mixed.Md', contents: '# Mixed');

        static::assertSame(
            ['mixed/Mixed.Md', 'upper/MD.MD'],
            new Markdown()->scan($this->tree),
        );
    }

    #[Test]
    public function refusesARootThatIsNotADirectory(): void
    {
        $this->makeTree();
        $this->writeFile('index.md', contents: '# Home');

        $this->expectException(InvalidArgumentException::class);

        new Markdown()->scan("{$this->tree}/index.md");
    }

    #[Test]
    public function returnsNothingForAnEmptyRoot(): void
    {
        $this->makeTree();

        static::assertSame([], new Markdown()->scan($this->tree));
    }

    protected function tearDown(): void
    {
        $this->removeTree();
    }
}
