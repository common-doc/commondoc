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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The filter is a pure function of a path list, so these run on arrays. The
 * traversal and its root refusals belong to `Tree`, which has its own test.
 */
#[CoversClass(Markdown::class)]
#[CoversMethod(Markdown::class, 'scan')]
final class MarkdownTest extends TestCase
{
    #[Test]
    public function excludesAPathThatMerelyContainsTheExtension(): void
    {
        static::assertSame(
            [],
            new Markdown()->scan(['notes.md.bak', 'markdown.md.txt', 'md']),
        );
    }

    #[Test]
    public function excludesEverythingThatIsNotMarkdown(): void
    {
        static::assertSame(
            ['index.md'],
            new Markdown()->scan(['index.md', 'style.css', 'notes.txt', 'image.png']),
        );
    }

    #[Test]
    public function findsDocumentsThatFollowSomethingElse(): void
    {
        // A file that is not a document must not end the pass. The traversal
        // order is byte value, so an asset can sort before every document.
        static::assertSame(
            ['b.md', 'z.md'],
            new Markdown()->scan(['a.png', 'b.md', 'c.css', 'z.md']),
        );
    }

    #[Test]
    public function keepsTheDocumentsAtTheRootAndNested(): void
    {
        static::assertSame(
            ['guide/setup.md', 'index.md'],
            new Markdown()->scan(['guide/setup.md', 'index.md']),
        );
    }

    #[Test]
    public function keepsTheOrderItIsGiven(): void
    {
        // The traversal sorts; sorting again here would be work that changes
        // nothing, and reordering would silently disagree with the walk.
        static::assertSame(
            ['B.md', 'a.md', 'z.md'],
            new Markdown()->scan(['B.md', 'a.md', 'z.md']),
        );
    }

    #[Test]
    public function matchesTheExtensionWithoutRegardToCase(): void
    {
        static::assertSame(
            ['mixed/Mixed.Md', 'upper/MD.MD'],
            new Markdown()->scan(['mixed/Mixed.Md', 'upper/MD.MD', 'plain.txt']),
        );
    }

    #[Test]
    public function returnsNothingForAnEmptyList(): void
    {
        static::assertSame([], new Markdown()->scan([]));
    }
}
