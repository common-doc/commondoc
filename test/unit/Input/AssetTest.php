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
use CommonDoc\Input\Markdown;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_intersect;
use function sort;

use const SORT_STRING;

#[CoversClass(Asset::class)]
#[CoversMethod(Asset::class, 'scan')]
final class AssetTest extends TestCase
{
    #[Test]
    public function findsFilesThatAreNotDocuments(): void
    {
        static::assertSame(
            ['images/logo.png', 'style.css'],
            new Asset()->scan(
                paths    : ['images/logo.png', 'index.md', 'style.css'],
                documents: ['index.md'],
            ),
        );
    }

    #[Test]
    public function keepsTheOrderItIsGiven(): void
    {
        static::assertSame(
            ['b.png', 'c.css'],
            new Asset()->scan(
                paths    : ['b.png', 'c.css', 'a.md'],
                documents: ['a.md'],
            ),
        );
    }

    /**
     * The property the difference exists to guarantee: the two lists together
     * account for every file, and no file appears in both.
     */
    #[Test]
    public function partitionsThePathsIntoDocumentsAndAssets(): void
    {
        // Sorted by byte value, which is the order the traversal produces.
        $paths = ['guide/logo.png', 'guide/setup.md', 'index.md', 'style.css'];

        $documents = new Markdown()->scan($paths);
        $assets    = new Asset()->scan(
            paths    : $paths,
            documents: $documents,
        );

        $recombined = [...$documents, ...$assets];
        sort($recombined, SORT_STRING);

        static::assertSame($paths, $recombined);
        static::assertSame([], array_intersect($documents, $assets));
    }

    #[Test]
    public function returnsEveryPathWhenNoneIsADocument(): void
    {
        static::assertSame(
            ['a.png', 'b.css'],
            new Asset()->scan(
                paths    : ['a.png', 'b.css'],
                documents: [],
            ),
        );
    }

    #[Test]
    public function returnsNothingForAnEmptyList(): void
    {
        static::assertSame([], new Asset()->scan(
            paths    : [],
            documents: [],
        ));
    }

    #[Test]
    public function returnsNothingWhenEveryPathIsADocument(): void
    {
        static::assertSame(
            [],
            new Asset()->scan(
                paths    : ['a.md', 'b.md'],
                documents: ['a.md', 'b.md'],
            ),
        );
    }
}
