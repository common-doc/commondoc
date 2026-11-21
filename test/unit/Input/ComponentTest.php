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

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Component;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Both forms come from one name, which is the whole point of the type: a site
 * cannot declare a label whose address says something else, because there is only
 * one value to declare.
 *
 * The spellings covered here are the ones an operator actually writes, and the
 * expectation in every case is that the published address does not depend on
 * which of them they chose.
 */
#[CoversClass(Component::class)]
#[CoversMethod(Component::class, '__construct')]
final class ComponentTest extends TestCase
{
    #[Test]
    public function collapsesEverySpellingToTheSameSegment(): void
    {
        // An operator's spelling must not reach the published address, so the
        // four forms a repository is commonly written in all answer one segment.
        foreach (['message-bus', 'message_bus', 'Message Bus', 'MESSAGE--BUS'] as $spelling) {
            static::assertSame('message-bus', new Component($spelling)->segment);
        }
    }

    #[Test]
    public function derivesTheSegmentAndTheLabelFromOneName(): void
    {
        $component = new Component('message-bus');

        static::assertSame('message-bus', $component->segment);
        static::assertSame('Message Bus', $component->label);
    }

    #[Test]
    public function keepsOneWordNamesReadable(): void
    {
        // A repository that is one word has no word break to preserve, so the
        // label is the segment with its first letter raised.
        $component = new Component('traccio');

        static::assertSame('traccio', $component->segment);
        static::assertSame('Traccio', $component->label);
    }

    #[Test]
    public function refusesANameWithNothingToAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carries no word that can address a path');

        new Component('---');
    }

    #[Test]
    public function stripsACloneSuffix(): void
    {
        // A remote URL carries a `.git` suffix, and a build reading one should not
        // publish a directory named for it.
        static::assertSame('message-bus', new Component('webinertia/message-bus.git')->segment);
    }

    #[Test]
    public function stripsAnOwnerPrefix(): void
    {
        // A component is a repository rather than an account, so the owner does
        // not belong in the address.
        $component = new Component('webinertia/message-bus');

        static::assertSame('message-bus', $component->segment);
        static::assertSame('Message Bus', $component->label);
    }

    #[Test]
    public function treatsAStudlyNameAsWords(): void
    {
        // The label follows `messagebus-event` rather than guessing at a camel
        // hump, because a name is not a reliable boundary and a wrong guess would
        // publish a label nobody wrote.
        $component = new Component('messagebus-event');

        static::assertSame('messagebus-event', $component->segment);
        static::assertSame('Messagebus Event', $component->label);
    }
}
