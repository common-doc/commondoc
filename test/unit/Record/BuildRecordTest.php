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

namespace CommonDocTest\Record;

use CommonDoc\Record\BuildRecord;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The record, including the invariant that makes it worth returning.
 *
 * Assets and documents share the failure list, so several cases come in pairs:
 * what a document failure looks like, and the same failure on an asset. The two
 * are told apart by which discovered list holds them, not by how they failed, and
 * only a test covering both proves the shared key space works.
 */
#[CoversClass(BuildRecord::class)]
#[CoversMethod(BuildRecord::class, '__construct')]
#[CoversMethod(BuildRecord::class, 'complete')]
#[CoversMethod(BuildRecord::class, 'failedPath')]
#[CoversMethod(BuildRecord::class, 'reasonFor')]
#[CoversMethod(BuildRecord::class, 'written')]
final class BuildRecordTest extends TestCase
{
    #[Test]
    public function acceptsAnEmptyBuild(): void
    {
        $record = new BuildRecord(
            input : [],
            asset : [],
            output: [],
            failed: [],
        );

        static::assertTrue($record->complete());
        static::assertSame(0, $record->written());
    }

    #[Test]
    public function leavesADiscoveredAssetThatWasNeitherWrittenNorFailedUnaccountedFor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"site.css" is unaccounted for.');

        new BuildRecord(
            input : [],
            asset : ['site.css'],
            output: [],
            failed: [],
        );
    }

    #[Test]
    public function ordersADocumentFailureAndAnAssetFailureInOneList(): void
    {
        // One list, one order: an operator reading the output should not have to
        // know which kind of file failed to find it.
        $record = new BuildRecord(
            input : ['guide/setup.md'],
            asset : ['images/d.png', 'site.css'],
            output: ['guide/setup.md'],
            failed: [
                'site.css'     => 'unreadable',
                'images/d.png' => 'unwritable',
            ],
        );

        static::assertSame(['images/d.png', 'site.css'], $record->failedPath());
        static::assertSame('unwritable', $record->reasonFor('images/d.png'));
        static::assertFalse($record->complete());
    }

    #[Test]
    public function ordersEveryListByByteValue(): void
    {
        $record = new BuildRecord(
            input : ['c.md', 'a.md', 'B.md'],
            asset : ['z.css', 'Y.png'],
            output: ['c.md', 'B.md', 'z.css', 'Y.png'],
            failed: ['a.md' => 'unreadable'],
        );

        static::assertSame(['B.md', 'a.md', 'c.md'], $record->input);
        static::assertSame(['Y.png', 'z.css'], $record->asset);
        static::assertSame(['B.md', 'Y.png', 'c.md', 'z.css'], $record->output);
        static::assertSame(['a.md'], $record->failedPath());
    }

    #[Test]
    public function ordersFailuresByPathWhenSeveralDocumentsFail(): void
    {
        $record = new BuildRecord(
            input : ['c.md', 'a.md', 'b.md'],
            asset : [],
            output: [],
            failed: [
                'c.md' => 'unreadable',
                'a.md' => 'unparsable',
                'b.md' => 'unwritable',
            ],
        );

        static::assertSame(['a.md', 'b.md', 'c.md'], $record->failedPath());
        static::assertSame('unparsable', $record->reasonFor('a.md'));
        static::assertSame('unwritable', $record->reasonFor('b.md'));
        static::assertSame('unreadable', $record->reasonFor('c.md'));
    }

    #[Test]
    public function recordsABuildInWhichNothingRendered(): void
    {
        $record = new BuildRecord(
            input : ['a.md'],
            asset : [],
            output: [],
            failed: ['a.md' => 'unreadable'],
        );

        static::assertFalse($record->complete());
        static::assertSame(0, $record->written());
    }

    #[Test]
    public function recordsACompleteBuild(): void
    {
        $record = new BuildRecord(
            input : ['b.md', 'a.md'],
            asset : ['d.png'],
            output: ['a.md', 'b.md', 'd.png'],
            failed: [],
        );

        static::assertTrue($record->complete());
        static::assertSame(3, $record->written());
        static::assertSame([], $record->failedPath());
        static::assertNull($record->reasonFor('a.md'));
    }

    #[Test]
    public function recordsAPartialBuildAndExposesTheReason(): void
    {
        $record = new BuildRecord(
            input : ['a.md', 'b.md'],
            asset : [],
            output: ['a.md'],
            failed: ['b.md' => 'the document could not be parsed'],
        );

        static::assertFalse($record->complete());
        static::assertSame(1, $record->written());
        static::assertSame('the document could not be parsed', $record->reasonFor('b.md'));
    }

    #[Test]
    public function refusesADocumentRecordedAsBothWrittenAndFailed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The paths "a.md" are recorded as both written and failed.');

        new BuildRecord(
            input : ['a.md'],
            asset : [],
            output: ['a.md'],
            failed: ['a.md' => 'unreadable'],
        );
    }

    #[Test]
    public function refusesAPathRecordedAsBothADocumentAndAnAsset(): void
    {
        // The two discovered lists partition the scan. A path in both would be
        // counted twice and would hide a partition that had stopped partitioning.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The paths "a.md" are recorded as both a document and an asset.');

        new BuildRecord(
            input : ['a.md'],
            asset : ['a.md'],
            output: ['a.md'],
            failed: [],
        );
    }

    #[Test]
    public function refusesARecordThatAccountsForAPathItDidNotFind(): void
    {
        // The message is asserted exactly rather than by substring: naming every
        // accounted path would also contain the offending one, so a loose check
        // would pass while telling the operator the wrong thing.
        try {
            new BuildRecord(
                input : ['a.md'],
                asset : [],
                output: ['a.md', 'ghost.md'],
                failed: [],
            );

            static::fail('A record that names a path it never discovered must be refused.');
        } catch (InvalidArgumentException $exception) {
            static::assertSame(
                'The record accounts for 2 paths while 1 were discovered; "ghost.md" was never discovered.',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function refusesARecordThatLeavesADiscoveredDocumentUnaccountedFor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The record accounts for 1 paths while 2 were discovered; "b.md" is unaccounted for.',
        );

        new BuildRecord(
            input : ['a.md', 'b.md'],
            asset : [],
            output: ['a.md'],
            failed: [],
        );
    }
}
