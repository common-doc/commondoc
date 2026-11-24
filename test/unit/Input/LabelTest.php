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

use CommonDoc\Input\Label;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The one rule that turns a name into a label.
 *
 * It is pinned here because three producers used to normalise on their own, and a menu entry
 * that disagrees with a page title is not something a build can detect.
 */
#[CoversClass(Label::class)]
#[CoversMethod(Label::class, 'of')]
final class LabelTest extends TestCase
{
    #[Test]
    public function answersTheNameAsWrittenWhenThereIsNothingToLabel(): void
    {
        static::assertSame('', Label::of(''));
    }

    #[Test]
    public function capitalisesOneWordNames(): void
    {
        static::assertSame('Index', Label::of('index'));
        static::assertSame('Readme', Label::of('README'));
    }

    #[Test]
    public function treatsEverySeparatorTheSameWay(): void
    {
        static::assertSame('Message Bus', Label::of('message_bus'));
        static::assertSame('Message Bus', Label::of('message bus'));
        static::assertSame('Message Bus', Label::of('message--bus'));
    }

    #[Test]
    public function trimsWhatSurroundsTheName(): void
    {
        static::assertSame('Setup', Label::of("  setup\n"));
    }

    #[Test]
    public function turnsSeparatorsIntoWords(): void
    {
        static::assertSame('Api Reference', Label::of('api-reference'));
    }
}
