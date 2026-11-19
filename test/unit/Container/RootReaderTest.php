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

use CommonDoc\Container\RootReader;
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Reading a list is not the same job as reading a scalar.
 *
 * An entry has a shape and a mount that the type validates, and a fallback that must
 * not discard the entries that were right. A whole-list fallback would cost every
 * root because one was mistyped, which is the failure a deployment cannot see.
 */
#[CoversClass(RootReader::class)]
#[CoversMethod(RootReader::class, 'defaults')]
#[CoversMethod(RootReader::class, 'read')]
final class RootReaderTest extends TestCase
{
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
    public function publishesAtTheSiteRootWhenAnEntryNamesNoMount(): void
    {
        $roots = new RootReader()->read([['path' => 'docs/v1']]);

        static::assertCount(1, $roots);
        static::assertSame('/', $roots[0]->mount);
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
    public function refusesAMountThatCannotPrefixAnAddress(): void
    {
        // A mount is not a typo in the same sense as a missing path: publishing at
        // an address the operator did not write is worse than refusing to start.
        $this->expectException(InvalidArgumentException::class);

        new RootReader()->read([['path' => 'docs', 'mount' => 'docs']]);
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
}
