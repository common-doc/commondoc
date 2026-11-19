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

namespace CommonDocTest;

use CommonDoc\Record\Progress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * What a phase did, and how two phases add up.
 *
 * The type exists so a phase contributes rather than reports. `BuildRecord` owns
 * the invariant that every discovered path is accounted for, and that cannot hold
 * until the last phase has run, so the merge must be a merge and not a mutation.
 */
#[CoversClass(Progress::class)]
#[CoversMethod(Progress::class, '__construct')]
#[CoversMethod(Progress::class, 'none')]
#[CoversMethod(Progress::class, 'and')]
final class ProgressTest extends TestCase
{
    #[Test]
    public function appendsTheLaterPhaseOutput(): void
    {
        $merged = new Progress(output: ['site.css'])->and(new Progress(output: ['index.md']));

        // Sorted, not concatenated, so a report does not reorder itself between
        // runs.
        static::assertSame(['index.md', 'site.css'], $merged->output);
    }

    #[Test]
    public function doesNothingBeforeAnyPhaseHasRun(): void
    {
        $none = Progress::none();

        static::assertSame([], $none->output);
        static::assertSame([], $none->failed);
    }

    #[Test]
    public function keepsTheLaterReasonForAPathBothPhasesReported(): void
    {
        $merged = new Progress(failed: ['site.css' => 'first'])
            ->and(new Progress(failed: ['site.css' => 'second']));

        static::assertSame(['site.css' => 'second'], $merged->failed);
    }

    #[Test]
    public function leavesAPathWrittenAndFailedToTheRecord(): void
    {
        // A contradiction, not something to resolve here: `BuildRecord` refuses a
        // path that is both written and failed, and merging it away would hide a
        // defect behind a merge rule.
        $merged = new Progress(output: ['site.css'])->and(new Progress(failed: ['site.css' => 'disk full']));

        static::assertSame(['site.css'], $merged->output);
        static::assertSame(['site.css' => 'disk full'], $merged->failed);
    }

    #[Test]
    public function leavesTheReceiverUnchanged(): void
    {
        $first  = new Progress(output: ['index.md']);
        $merged = $first->and(new Progress(output: ['site.css']));

        static::assertSame(['index.md'], $first->output);
        static::assertNotSame($first, $merged);
    }

    #[Test]
    public function sortsTheMergedFailuresByPath(): void
    {
        $merged = new Progress(failed: ['site.css' => 'disk full'])
            ->and(new Progress(failed: ['images/d.png' => 'not readable']));

        // Ordered by path so an operator reads one list, not two phases.
        static::assertSame(['images/d.png', 'site.css'], array_keys($merged->failed));
    }
}
