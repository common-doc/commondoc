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

namespace CommonDocTest\Exception;

use CommonDoc\Exception\ExceptionInterface;
use CommonDoc\Exception\HttpException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * The transport's failure, and the two contracts it has to satisfy at once.
 *
 * PSR-18 requires a `ClientExceptionInterface`, and this package requires its own marker so the
 * console renders the failure as a refusal instead of letting it tear the session down. Both are
 * load-bearing, so both are pinned here rather than assumed from the class declaration.
 */
#[CoversClass(HttpException::class)]
#[CoversMethod(HttpException::class, '__construct')]
final class HttpExceptionTest extends TestCase
{
    #[Test]
    public function carriesNoCodeBecauseTheMessageIsTheSignal(): void
    {
        // PSR-18 has no notion of an exception code, so the code is always zero and never a
        // number a caller might interpret.
        static::assertSame(0, new HttpException('a transfer failed')->getCode());
    }

    #[Test]
    public function carriesTheMessageItWasGiven(): void
    {
        static::assertSame('a transfer failed', new HttpException('a transfer failed')->getMessage());
    }

    #[Test]
    public function hasNoCauseWhenNoneWasGiven(): void
    {
        static::assertNull(new HttpException('no response')->getPrevious());
    }

    #[Test]
    public function keepsTheCause(): void
    {
        $cause = new RuntimeException('the stream refused');

        static::assertSame($cause, new HttpException('no response', $cause)->getPrevious());
    }

    #[Test]
    public function satisfiesTheContractPsr18Requires(): void
    {
        static::assertInstanceOf(ClientExceptionInterface::class, new HttpException('a transfer failed'));
    }

    #[Test]
    public function satisfiesThisPackagesRefusalContract(): void
    {
        // This is what makes an unreachable repository an `INVALID` exit rather than an uncaught
        // exception, because the console catches this interface and renders it.
        static::assertInstanceOf(ExceptionInterface::class, new HttpException('a transfer failed'));
    }
}
