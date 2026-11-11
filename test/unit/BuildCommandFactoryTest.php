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
use CommonDoc\Console\BuildCommand;
use CommonDoc\Container\BuildCommandFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\MessageBusInterface;
use Webware\MessageBus\MessageStatus;

#[CoversClass(BuildCommandFactory::class)]
#[CoversMethod(BuildCommandFactory::class, '__invoke')]
final class BuildCommandFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheCommandAroundTheBusFromTheContainer(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus
            ->method('handle')
            ->willReturn(new CommandResult(
                command: new BuildSiteCommand(
                    source: 'docs',
                    output: 'out',
                ),
                status : MessageStatus::Success,
                result : [],
            ));

        $command = new BuildCommandFactory()($this->containerWithBus($bus), BuildCommand::class);

        $tester = new CommandTester($command);
        $tester->execute(['source' => 'docs', '--output' => 'out']);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    private function containerWithBus(MessageBusInterface $bus): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => MessageBusInterface::class === $id ? $bus : null,
            );

        return $container;
    }
}
