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
use CommonDoc\Input\Root;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Record\BuildRecord;
use CommonDoc\SiteConfig;
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
 * What the command sends, prints and exits with.
 *
 * The command is a transport over the configured site. A run with no arguments
 * has to work, because a workflow runs it from configuration alone; the two
 * options are development overrides and must not become the source of truth.
 *
 * The record is the only reporting channel, so the non-record case is a test of
 * its own: printing nothing for an unrecognised result is exactly what let an
 * earlier version report a silent no-op as a successful build.
 *
 * The exit code comes from the result's status, not from the record, so the
 * fixtures pair the two the way the handler pairs them. A fixture that reported a
 * record with failures as a success would be describing something the build cannot
 * produce.
 */
#[CoversClass(BuildCommand::class)]
#[CoversMethod(BuildCommand::class, '__construct')]
#[CoversMethod(BuildCommand::class, 'configure')]
#[CoversMethod(BuildCommand::class, 'execute')]
#[CoversMethod(BuildCommand::class, 'request')]
final class BuildCommandTest extends TestCase
{
    private const string CONFIGURED_OUTPUT = 'site';

    private static function site(): SiteConfig
    {
        return new SiteConfig(
            documentRoot: new DocumentRoot('/'),
            roots       : [new Root(path: 'docs/v1')],
            output      : self::CONFIGURED_OUTPUT,
        );
    }

    #[Test]
    public function buildsTheConfiguredSiteWhenGivenNothing(): void
    {
        $bus = $this->recordingBus();

        $tester = new CommandTester(new BuildCommand($bus, self::site()));
        $tester->execute([]);

        $request = $this->onlyRequest($bus);

        static::assertEquals([new Root(path: 'docs/v1')], $request->getRoots());
        static::assertSame(self::CONFIGURED_OUTPUT, $request->getOutput());
        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
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
    public function ignoresAnEmptySourceAndKeepsTheConfiguredRoots(): void
    {
        $bus = $this->recordingBus();

        $tester = new CommandTester(new BuildCommand($bus, self::site()));
        $tester->execute(['source' => '']);

        static::assertEquals([new Root(path: 'docs/v1')], $this->onlyRequest($bus)->getRoots());
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
    public function replacesTheConfiguredOutputWhenOneIsGiven(): void
    {
        $bus = $this->recordingBus();

        $tester = new CommandTester(new BuildCommand($bus, self::site()));
        $tester->execute(['--output' => 'elsewhere']);

        static::assertSame('elsewhere', $this->onlyRequest($bus)->getOutput());
    }

    #[Test]
    public function replacesTheConfiguredRootsWithOneRootWhenASourceIsGiven(): void
    {
        $bus = $this->recordingBus();

        $tester = new CommandTester(new BuildCommand($bus, self::site()));
        $tester->execute(['source' => 'docs/other']);

        $request = $this->onlyRequest($bus);

        // Published at `/` because a single-repository run is what this option is
        // for. It cannot express a mount, so it cannot compose a site.
        static::assertEquals([new Root(path: 'docs/other')], $request->getRoots());
        static::assertSame(self::CONFIGURED_OUTPUT, $request->getOutput());
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

    #[Test]
    public function requiresNeitherAnArgumentNorAnOption(): void
    {
        // The common run is the workflow's: it supplies nothing and builds what was
        // configured, so a required argument would move the site definition into
        // workflow YAML.
        $definition = new BuildCommand($this->stubBus(), self::site())->getDefinition();

        static::assertTrue($definition->hasArgument('source'));
        static::assertFalse($definition->getArgument('source')->isRequired());
        static::assertTrue($definition->hasOption('output'));
        static::assertNull($definition->getOption('output')->getDefault());
    }

    private function onlyRequest(MessageBusInterface $bus): BuildSiteCommand
    {
        static::assertCount(1, $this->sent);

        return $this->sent[0];
    }

    private function recordingBus(): MessageBusInterface
    {
        $this->sent = [];

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('handle')->willReturnCallback(
            function (object $message): ResultInterface {
                static::assertInstanceOf(BuildSiteCommand::class, $message);

                $this->sent[] = $message;

                return new CommandResult(
                    command: $message,
                    status : MessageStatus::Success,
                    result : new BuildRecord(
                        input : [],
                        asset : [],
                        output: [],
                        failed: [],
                    ),
                );
            },
        );

        return $bus;
    }

    /** @var list<BuildSiteCommand> */
    private array $sent = [];

    private function runWith(mixed $result): CommandTester
    {
        // The status is derived the way the handler derives it, so the fixture
        // cannot present a pairing the handler would never produce: a record with
        // failures reported as a success.
        $status = $result instanceof BuildRecord && ! $result->complete()
            ? MessageStatus::Failure
            : MessageStatus::Success;

        $bus = $this->stubBus(new CommandResult(
            command: new BuildSiteCommand(
                roots : [new Root(path: 'docs')],
                output: 'out',
            ),
            status : $status,
            result : $result,
        ));

        $tester = new CommandTester(new BuildCommand($bus, self::site()));
        $tester->execute([]);

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
