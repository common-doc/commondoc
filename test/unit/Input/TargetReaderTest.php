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
use CommonDoc\Input\Target;
use CommonDoc\Input\TargetReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * The one rule that decides what a configured target names.
 *
 * It is pinned on its own because it is read from three places: the site's fallback, an
 * entry's own target, and an organization's entry. When it was written twice, one copy
 * accepted a list and the other did not.
 */
#[CoversClass(TargetReader::class)]
#[CoversMethod(TargetReader::class, 'read')]
final class TargetReaderTest extends TestCase
{
    #[Test]
    public function answersNoCandidatesForAValueItCannotUse(): void
    {
        // No refusal: a value that names nothing leaves the caller's own default to apply,
        // which is what keeps a typo costing one setting instead of the whole build.
        static::assertSame([], new TargetReader()->read(null));
        static::assertSame([], new TargetReader()->read(''));
        static::assertSame([], new TargetReader()->read(42));
        static::assertCount(1, new TargetReader()->read(['docs/1.0', '']));
    }

    #[Test]
    public function keepsTheOrderOfTheCandidates(): void
    {
        $targets = new TargetReader()->read(['a', 'b', 'c']);

        static::assertSame(
            ['a', 'b', 'c'],
            array_map(static fn(Target $target): string => $target->location, $targets),
        );
    }

    #[Test]
    public function readsAListAsItsCandidates(): void
    {
        $targets = new TargetReader()->read(['docs/{major}.0', 'docs/v{major}']);

        static::assertCount(2, $targets);
        static::assertSame('docs/{major}.0', $targets[0]->location);
        static::assertSame('docs/v{major}', $targets[1]->location);
    }

    #[Test]
    public function readsOneStringAsASingleCandidate(): void
    {
        $targets = new TargetReader()->read('docs/1.0');

        static::assertCount(1, $targets);
        static::assertSame('docs/1.0', $targets[0]->location);
    }

    #[Test]
    public function refusesACandidateThatLeavesTheRepository(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TargetReader()->read(['docs/../../etc']);
    }
}
