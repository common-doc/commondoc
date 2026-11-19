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
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Exception\LogicException;
use CommonDoc\Exception\RuntimeException;
use InvalidArgumentException as SplInvalidArgumentException;
use LogicException as SplLogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException as SplRuntimeException;
use Throwable;

use function is_a;

/**
 * The contract the package's exceptions have to keep.
 *
 * The interface exists so a caller can catch "commondoc refused" without naming
 * three SPL classes and without catching a collaborator's failure by accident.
 * That only works while every type implements it *and* while the interface
 * remains throwable, so both halves are asserted rather than assumed.
 *
 * There is no coverage metadata for the interface, because PHPUnit rejects an
 * interface as a target: it declares no statements to cover. The three concrete
 * types declare none either, beyond inheriting their parent's constructor, so
 * they are covered as classes rather than through named methods.
 */
#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(LogicException::class)]
#[CoversClass(RuntimeException::class)]
final class ExceptionTest extends TestCase
{
    /**
     * @return array<non-empty-string, array{class-string<ExceptionInterface>, class-string<Throwable>}>
     */
    public static function exceptionTypes(): array
    {
        return [
            'invalid argument' => [InvalidArgumentException::class, SplInvalidArgumentException::class],
            'logic'            => [LogicException::class, SplLogicException::class],
            'runtime'          => [RuntimeException::class, SplRuntimeException::class],
        ];
    }

    /**
     * @param class-string<ExceptionInterface> $own
     * @param class-string<Throwable>          $spl
     */
    #[Test]
    #[DataProvider('exceptionTypes')]
    public function eachTypeCarriesItsMessageAndPreviousFailure(string $own, string $spl): void
    {
        // `$spl` is the shape's second element, used by the sibling test. PHPUnit
        // passes every element of a data set, so it is accepted and ignored here
        // rather than reshaping the provider for one caller.
        static::assertTrue(is_a($own, $spl, allow_string: true));

        $previous = new SplRuntimeException('earlier');
        $instance = new $own('later', 3, $previous);

        static::assertSame('later', $instance->getMessage());
        static::assertSame(3, $instance->getCode());
        static::assertSame($previous, $instance->getPrevious());
    }

    /**
     * @param class-string<ExceptionInterface> $own
     * @param class-string<Throwable>          $spl
     */
    #[Test]
    #[DataProvider('exceptionTypes')]
    public function eachTypeKeepsBothItsSplParentAndThePackageInterface(string $own, string $spl): void
    {
        // Extending SPL is what keeps every existing catch working: introducing
        // these types needed no call site rewritten.
        static::assertTrue(is_a($own, $spl, allow_string: true));
        static::assertTrue(is_a($own, ExceptionInterface::class, allow_string: true));
    }

    #[Test]
    public function theInterfaceIsThrowableSoItCanBeCaught(): void
    {
        // A marker interface that does not extend Throwable cannot be used in a
        // catch type, which is the mistake this asserts against.
        static::assertTrue(is_a(ExceptionInterface::class, Throwable::class, allow_string: true));
    }
}
