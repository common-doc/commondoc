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
use CommonDoc\Input\Root;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Record\BuildRecord;
use CommonDoc\SiteConfig;
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

/**
 * The command receives both of its collaborators from the container.
 *
 * It takes the bus and the site, so the test supplies both: a factory that only
 * resolved the bus would leave the command unable to know what it was asked to
 * build, which is the whole point of the configured site.
 */
#[CoversClass(BuildCommandFactory::class)]
#[CoversMethod(BuildCommandFactory::class, '__invoke')]
final class BuildCommandFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheCommandAroundTheBusAndTheSite(): void
    {
        $site = new SiteConfig(
            documentRoot: new DocumentRoot('/'),
            roots       : [new Root(path: 'docs')],
            output      : 'out',
        );

        $bus = $this->createStub(MessageBusInterface::class);
        $bus
            ->method('handle')
            ->willReturn(new CommandResult(
                command: new BuildSiteCommand(
                    roots : $site->roots,
                    output: $site->output,
                ),
                status : MessageStatus::Success,
                result : new BuildRecord(
                    input : [],
                    asset : [],
                    output: [],
                    failed: [],
                ),
            ));

        $command = new BuildCommandFactory()($this->container($bus, $site), BuildCommand::class);

        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    private function container(MessageBusInterface $bus, SiteConfig $site): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    MessageBusInterface::class => $bus,
                    SiteConfig::class          => $site,
                    default                    => null,
                },
            );

        return $container;
    }
}
