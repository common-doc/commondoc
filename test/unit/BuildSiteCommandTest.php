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

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The request carries what this run does, and nothing else.
 *
 * A build may cover more than one source root, and a phase that needs to hand
 * something forward has to return a new request rather than write to this one,
 * because the bus passes messages and the handler does not own the pipeline.
 */
#[CoversClass(BuildSiteCommand::class)]
#[CoversMethod(BuildSiteCommand::class, '__construct')]
#[CoversMethod(BuildSiteCommand::class, 'getCommandName')]
#[CoversMethod(BuildSiteCommand::class, 'getOutput')]
#[CoversMethod(BuildSiteCommand::class, 'getRoots')]
#[CoversMethod(BuildSiteCommand::class, 'withOutput')]
#[CoversMethod(BuildSiteCommand::class, 'withRoots')]
final class BuildSiteCommandTest extends TestCase
{
    #[Test]
    public function exposesItsRootsAndOutput(): void
    {
        $root    = new Root(path: 'docs');
        $command = new BuildSiteCommand(
            roots : [$root],
            output: 'build',
        );

        static::assertSame([$root], $command->getRoots());
        static::assertSame('build', $command->getOutput());
    }

    #[Test]
    public function namesItselfForTheBus(): void
    {
        static::assertSame(
            BuildSiteCommand::class,
            new BuildSiteCommand(
                roots : [new Root(path: 'docs')],
                output: 'build',
            )->getCommandName(),
        );
    }

    #[Test]
    public function refusesAnEmptyOutput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BuildSiteCommand(
            roots : [new Root(path: 'docs')],
            output: 'build',
        )->withOutput('');
    }

    #[Test]
    public function refusesAnEmptyRootListWhenReplacing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BuildSiteCommand(
            roots : [new Root(path: 'docs')],
            output: 'build',
        )->withRoots([]);
    }

    #[Test]
    public function refusesARequestWithNoRoots(): void
    {
        // A build with nothing to read is not a build that produced nothing; it is
        // a request that cannot be honoured, and it would otherwise report success
        // over an empty record.
        $this->expectException(InvalidArgumentException::class);

        new BuildSiteCommand(
            roots : [],
            output: 'build',
        );
    }

    #[Test]
    public function replacesTheRootsWithoutTouchingTheOutput(): void
    {
        $command = new BuildSiteCommand(
            roots : [new Root(path: 'docs')],
            output: 'build',
        );

        $other = new Root(
            path : 'other',
            mount: '/other/',
        );
        $moved = $command->withRoots([$other]);

        static::assertSame([$other], $moved->getRoots());
        static::assertSame('build', $moved->getOutput());
        static::assertCount(1, $command->getRoots());
    }

    #[Test]
    public function writesTheSameRequestSomewhereElse(): void
    {
        $root    = new Root(path: 'docs');
        $command = new BuildSiteCommand(
            roots : [$root],
            output: 'build',
        );

        $moved = $command->withOutput('elsewhere');

        static::assertSame('elsewhere', $moved->getOutput());
        static::assertSame([$root], $moved->getRoots());
        // The original is untouched: a phase returns a new request, it does not
        // rewrite the one it was given.
        static::assertSame('build', $command->getOutput());
    }
}
