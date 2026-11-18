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
            output: [],
            failed: [],
        );

        static::assertTrue($record->complete());
        static::assertSame(0, $record->written());
    }

    #[Test]
    public function ordersEveryListByByteValue(): void
    {
        $record = new BuildRecord(
            input : ['c.md', 'a.md', 'B.md'],
            output: ['c.md', 'B.md'],
            failed: ['a.md' => 'unreadable'],
        );

        static::assertSame(['B.md', 'a.md', 'c.md'], $record->input);
        static::assertSame(['B.md', 'c.md'], $record->output);
        static::assertSame(['a.md'], $record->failedPath());
    }

    #[Test]
    public function ordersFailuresByPathWhenSeveralDocumentsFail(): void
    {
        $record = new BuildRecord(
            input : ['c.md', 'a.md', 'b.md'],
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
            output: ['a.md', 'b.md'],
            failed: [],
        );

        static::assertTrue($record->complete());
        static::assertSame(2, $record->written());
        static::assertSame([], $record->failedPath());
        static::assertNull($record->reasonFor('a.md'));
    }

    #[Test]
    public function recordsAPartialBuildAndExposesTheReason(): void
    {
        $record = new BuildRecord(
            input : ['a.md', 'b.md'],
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
        $this->expectExceptionMessage('The documents "a.md" are recorded as both written and failed.');

        new BuildRecord(
            input : ['a.md'],
            output: ['a.md'],
            failed: ['a.md' => 'unreadable'],
        );
    }

    #[Test]
    public function refusesARecordThatAccountsForADocumentItDidNotFind(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BuildRecord(
            input : ['a.md'],
            output: ['a.md', 'ghost.md'],
            failed: [],
        );
    }

    #[Test]
    public function refusesARecordThatLeavesADiscoveredDocumentUnaccountedFor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The record accounts for 1 documents while 2 were discovered; "b.md" is unaccounted for.',
        );

        new BuildRecord(
            input : ['a.md', 'b.md'],
            output: ['a.md'],
            failed: [],
        );
    }
}
