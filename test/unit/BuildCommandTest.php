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
use CommonDoc\Record\BuildRecord;
use InvalidArgumentException;
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

/**
 * What the command prints and what it exits with.
 *
 * The record is the only reporting channel, so the non-record case is a test of
 * its own: printing nothing for an unrecognised result is exactly what let the
 * previous version report a silent no-op as a successful build.
 */
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
    public function exitsZeroAndPrintsNothingForAnEmptySuccessfulBuild(): void
    {
        $tester = $this->runWith(new BuildRecord(
            input : [],
            asset : [],
            output: [],
            failed: [],
        ));

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame('', $tester->getDisplay());
    }

    #[Test]
    public function refusesAResultThatIsNotARecord(): void
    {
        $tester = $this->runWith(['out/index.html']);

        // Silent success is the failure mode this replaces, so an unexpected
        // result is said out loud rather than filtered away.
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('nothing can be reported', $tester->getDisplay());
        static::assertStringContainsString('BuildRecord', $tester->getDisplay());
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
    public function reportsAFailedAssetTheSameWayAsAFailedDocument(): void
    {
        // Both kinds go through one list, so the operator reads one report and
        // does not have to know which kind of file failed to act on it.
        $tester = $this->runWith(new BuildRecord(
            input : ['index.md'],
            asset : ['site.css'],
            output: ['index.md'],
            failed: ['site.css' => 'unable to replace the file'],
        ));

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('Failed site.css: unable to replace the file', $tester->getDisplay());
        static::assertStringContainsString('Wrote index.md', $tester->getDisplay());
    }

    #[Test]
    public function reportsEveryDocumentThatWasNotWritten(): void
    {
        $tester = $this->runWith(new BuildRecord(
            input : ['guide/setup.md', 'index.md'],
            asset : [],
            output: ['index.md'],
            failed: ['guide/setup.md' => 'The link target does not exist.'],
        ));

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString(
            'Failed guide/setup.md: The link target does not exist.',
            $tester->getDisplay(),
        );
        static::assertStringContainsString('Wrote index.md', $tester->getDisplay());
    }

    #[Test]
    public function reportsEveryDocumentThatWasWritten(): void
    {
        $tester = $this->runWith(new BuildRecord(
            input : ['guide/setup.md', 'index.md'],
            asset : [],
            output: ['guide/setup.md', 'index.md'],
            failed: [],
        ));

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('Wrote guide/setup.md', $tester->getDisplay());
        static::assertStringContainsString('Wrote index.md', $tester->getDisplay());
    }

    private function runWith(mixed $result): CommandTester
    {
        $command = new BuildSiteCommand(
            source: 'docs',
            output: 'out',
        );
        $bus = $this->stubBus(new CommandResult(
            command: $command,
            status : MessageStatus::Success,
            result : $result,
        ));

        $tester = new CommandTester(new BuildCommand($bus));
        $tester->execute(['source' => 'docs', '--output' => 'out']);

        return $tester;
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
