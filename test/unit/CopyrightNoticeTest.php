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

use CommonDoc\View\CopyrightNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The words in the footer, which are a name and one year or a span of them.
 */
#[CoversClass(CopyrightNotice::class)]
#[CoversMethod(CopyrightNotice::class, 'text')]
final class CopyrightNoticeTest extends TestCase
{
    #[Test]
    public function doesNotWriteASpanForABuildOlderThanTheFirstYear(): void
    {
        // A machine whose clock is behind would otherwise write `2026 - 2025`.
        static::assertSame(
            '@copyright Common Doc 2026',
            $this->notice()->text(
                projectName: 'Common Doc',
                year       : 2025,
            ),
        );
    }

    #[Test]
    public function leavesTheNameOutWhenTheSiteStatesNone(): void
    {
        static::assertSame('@copyright 2026', $this->notice()->text(
            projectName: '',
            year       : 2026,
        ));
    }

    #[Test]
    public function namesASpanOnceTheYearHasMovedOn(): void
    {
        static::assertSame(
            '@copyright Common Doc 2026 - 2027',
            $this->notice()->text(
                projectName: 'Common Doc',
                year       : 2027,
            ),
        );
    }

    #[Test]
    public function namesOneYearInTheYearTheSiteIsFirstPublished(): void
    {
        static::assertSame(
            '@copyright Common Doc 2026',
            $this->notice()->text(
                projectName: 'Common Doc',
                year       : 2026,
            ),
        );
    }

    private function notice(): CopyrightNotice
    {
        return new CopyrightNotice();
    }
}
