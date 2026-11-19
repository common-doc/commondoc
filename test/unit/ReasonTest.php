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

use CommonDoc\Exception\RuntimeException;
use CommonDoc\Record\Reason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The one rule both failure-recording phases share.
 *
 * It is here rather than at each catch because a second copy would drift, and the
 * drift would be silent: one phase would record a failure the other let propagate.
 */
#[CoversClass(Reason::class)]
#[CoversMethod(Reason::class, 'of')]
final class ReasonTest extends TestCase
{
    #[Test]
    public function givesTheMessageOfTheFailure(): void
    {
        static::assertSame(
            'The link target does not exist.',
            Reason::of(new RuntimeException('The link target does not exist.')),
        );
    }

    #[Test]
    public function namesTheClassWhenThereIsNoMessage(): void
    {
        // `BuildRecord` requires a non-empty reason, and an empty string would
        // read as though nothing went wrong.
        static::assertSame(RuntimeException::class, Reason::of(new RuntimeException()));
    }
}
