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
use CommonDoc\Listener\CommandPostHandleListener;
use CommonDoc\Listener\CommandPreHandleListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Event\ListenerInterface;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\Event\Command\CommandPostHandleEvent;
use Webware\MessageBus\Event\Command\CommandPreHandleEvent;
use Webware\MessageBus\MessageStatus;

#[CoversClass(CommandPreHandleListener::class)]
#[CoversClass(CommandPostHandleListener::class)]
#[CoversMethod(CommandPreHandleListener::class, '__invoke')]
#[CoversMethod(CommandPostHandleListener::class, '__invoke')]
final class CommandEventListenerTest extends TestCase
{
    #[Test]
    public function postHandleListenerAcceptsThePostHandleEvent(): void
    {
        $command = new BuildSiteCommand(
            source: 'docs',
            output: 'build',
        );
        $result = new CommandResult(
            command: $command,
            status : MessageStatus::Success,
            result : [],
        );
        $event    = new CommandPostHandleEvent($result);
        $listener = new CommandPostHandleListener();

        static::assertInstanceOf(ListenerInterface::class, $listener);

        $listener($event);

        static::assertSame($command, $event->getCommand());
        static::assertSame($result, $event->getResult());
    }

    #[Test]
    public function preHandleListenerAcceptsThePreHandleEvent(): void
    {
        $command = new BuildSiteCommand(
            source: 'docs',
            output: 'build',
        );
        $event    = new CommandPreHandleEvent($command);
        $listener = new CommandPreHandleListener();

        static::assertInstanceOf(ListenerInterface::class, $listener);

        $listener($event);

        static::assertSame($command, $event->getCommand());
    }
}
