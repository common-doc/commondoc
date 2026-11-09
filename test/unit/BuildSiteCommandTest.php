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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuildSiteCommand::class)]
#[CoversMethod(BuildSiteCommand::class, '__construct')]
#[CoversMethod(BuildSiteCommand::class, 'getSource')]
#[CoversMethod(BuildSiteCommand::class, 'getOutput')]
#[CoversMethod(BuildSiteCommand::class, 'getCommandName')]
final class BuildSiteCommandTest extends TestCase
{
    #[Test]
    public function exposesItsSourceAndOutput(): void
    {
        $command = new BuildSiteCommand(
            source: 'docs',
            output: 'build',
        );

        static::assertSame('docs', $command->getSource());
        static::assertSame('build', $command->getOutput());
    }

    #[Test]
    public function namesItselfForTheBus(): void
    {
        static::assertSame(
            BuildSiteCommand::class,
            new BuildSiteCommand(
                source: 'docs',
                output: 'build',
            )->getCommandName(),
        );
    }
}
