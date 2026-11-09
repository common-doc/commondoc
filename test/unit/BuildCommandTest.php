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
use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\Console\BuildCommand;
use InvalidArgumentException;
use League\CommonMark\ConverterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\MessageBusInterface;
use Webware\MessageBus\MessageStatus;
use Webware\MessageBus\ResultInterface;

#[CoversClass(BuildCommand::class)]
#[CoversMethod(BuildCommand::class, '__construct')]
#[CoversMethod(BuildCommand::class, 'configure')]
#[CoversMethod(BuildCommand::class, 'execute')]
final class BuildCommandTest extends TestCase
{
    #[Test]
    public function definesTheSourceArgumentAndTheOutputOption(): void
    {
        $definition = new BuildCommand($this->stubBus())->getDefinition();

        static::assertTrue($definition->hasArgument('source'));
        static::assertTrue($definition->getArgument('source')->isRequired());
        static::assertTrue($definition->hasOption('output'));
        static::assertSame('build', $definition->getOption('output')->getDefault());
    }

    #[Test]
    public function rejectsAnEmptyOutputOption(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $tester = new CommandTester(new BuildCommand($this->stubBus()));
        $tester->execute(['source' => 'docs', '--output' => '']);
    }

    #[Test]
    public function rejectsAnEmptySourceArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $tester = new CommandTester(new BuildCommand($this->stubBus()));
        $tester->execute(['source' => '']);
    }

    #[Test]
    public function reportsEveryWrittenPathOnSuccess(): void
    {
        $command = new BuildSiteCommand(
            source: 'docs',
            output: 'out',
        );
        $bus = $this->stubBus(
            new CommandResult(
                command: $command,
                status : MessageStatus::Success,
                result : ['out/index.html', 'out/about.html'],
            ),
        );

        $tester = new CommandTester(new BuildCommand($bus));
        $tester->execute(['source' => 'docs', '--output' => 'out']);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('Wrote out/index.html', $tester->getDisplay());
        static::assertStringContainsString('Wrote out/about.html', $tester->getDisplay());
    }

    #[Test]
    public function reportsOnlyStringEntriesAsErrorsOnFailure(): void
    {
        $command = new BuildSiteCommand(
            source: 'docs',
            output: 'out',
        );
        $bus = $this->stubBus(
            new CommandResult(
                command: $command,
                status : MessageStatus::Failure,
                result : ['the source directory is missing', 42],
            ),
        );

        $tester = new CommandTester(new BuildCommand($bus));
        $tester->execute(['source' => 'docs', '--output' => 'out']);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('the source directory is missing', $tester->getDisplay());
        static::assertStringNotContainsString('42', $tester->getDisplay());
    }

    #[Test]
    public function succeedsAndPrintsNothingWhenTheResultIsEmpty(): void
    {
        $command = new BuildSiteCommand(
            source: 'docs',
            output: 'out',
        );
        $handler = new BuildSiteHandler($this->createStub(ConverterInterface::class));
        $bus     = $this->stubBus($handler->handle($command));

        $tester = new CommandTester(new BuildCommand($bus));
        $tester->execute(['source' => 'docs', '--output' => 'out']);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame('', $tester->getDisplay());
    }

    private function stubBus(?ResultInterface $result = null): MessageBusInterface
    {
        $bus = $this->createStub(MessageBusInterface::class);

        if (null !== $result) {
            $bus->method('handle')->willReturn($result);
        }

        return $bus;
    }
}
