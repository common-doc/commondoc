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

namespace CommonDocTest\View;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\View\Heading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * One heading, as a link target.
 *
 * The two refusals both prevent a link that would be published and do nothing: a level
 * outside 1 to 6 is not a heading, and an empty fragment names no anchor.
 */
#[CoversClass(Heading::class)]
final class HeadingTest extends TestCase
{
    #[Test]
    public function acceptsBothEndsOfTheLevelRange(): void
    {
        static::assertSame(
            1,
            new Heading(
                level   : 1,
                label   : 'Top',
                fragment: 'top',
            )->level,
        );
        static::assertSame(
            6,
            new Heading(
                level   : 6,
                label   : 'Deep',
                fragment: 'deep',
            )->level,
        );
    }

    #[Test]
    public function carriesTheLevelTheTextAndTheFragment(): void
    {
        $heading = new Heading(
            level   : 2,
            label   : 'The index rule',
            fragment: 'the-index-rule',
        );

        static::assertSame(2, $heading->level);
        static::assertSame('The index rule', $heading->label);
        static::assertSame('the-index-rule', $heading->fragment);
    }

    #[Test]
    public function refusesALevelAboveSix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A heading level is 1 to 6');

        new Heading(
            level   : 7,
            label   : 'Too deep',
            fragment: 'too-deep',
        );
    }

    #[Test]
    public function refusesALevelBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A heading level is 1 to 6');

        new Heading(
            level   : 0,
            label   : 'Nowhere',
            fragment: 'nowhere',
        );
    }

    #[Test]
    public function refusesAnEmptyFragment(): void
    {
        // An empty fragment would publish a link to the page rather than to a section,
        // which is a link that looks correct and reaches nothing.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carries no id');

        new Heading(
            level   : 2,
            label   : 'Nameless',
            fragment: '',
        );
    }
}
