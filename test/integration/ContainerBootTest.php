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

namespace CommonDocTestIntegration;

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\ConfigProvider;
use CommonDoc\Console\BuildCommand;
use CommonDoc\Listener\CommandPostHandleListener;
use CommonDoc\Listener\CommandPreHandleListener;
use CommonDoc\Middleware\LayoutWrappingMiddleware;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionProperty;
use SplQueue;
use Symfony\Component\Console\Application;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\Event\Command\CommandPostHandleEvent;
use Webware\MessageBus\Event\Command\CommandPreHandleEvent;
use Webware\MessageBus\Event\Middleware\CommandPostHandleMiddleware;
use Webware\MessageBus\Event\Middleware\CommandPreHandleMiddleware;
use Webware\MessageBus\MessageBusInterface;
use Webware\MessageBus\MessageStatus;
use Webware\MessageBus\Middleware\MessageHandlerMiddleware;
use Webware\MessageBus\MiddlewareInterface;
use Webware\MessageBus\MiddlewarePipe;

/**
 * Boots the real container from config/container.php and asserts that every
 * scaffolded seam resolves.
 *
 * A declared dependency that is never aggregated fails here rather than at the
 * first invocation, which is the whole point of the scaffold.
 */
#[CoversClass(ConfigProvider::class)]
#[CoversClass(BuildSiteHandler::class)]
#[CoversClass(CommandPreHandleListener::class)]
#[CoversClass(CommandPostHandleListener::class)]
#[CoversClass(SourceLoadingMiddleware::class)]
#[CoversClass(LayoutWrappingMiddleware::class)]
final class ContainerBootTest extends TestCase
{
    #[Test]
    public function bootsTheConsoleApplicationWithTheBuildCommand(): void
    {
        $application = $this->container()->get(Application::class);

        static::assertInstanceOf(Application::class, $application);
        static::assertTrue($application->has(name: BuildCommand::NAME));
        static::assertInstanceOf(BuildCommand::class, $application->get(name: BuildCommand::NAME));
    }

    #[Test]
    public function buildsTheMessageBusWithItsEventMiddleware(): void
    {
        static::assertInstanceOf(
            EventDispatcherInterface::class,
            $this->container()->get(EventDispatcherInterface::class),
        );
        static::assertInstanceOf(
            MessageBusInterface::class,
            $this->container()->get(MessageBusInterface::class),
        );
    }

    #[Test]
    public function dispatchesTheBuildSiteCommandToItsHandler(): void
    {
        /** @var MessageBusInterface $bus */
        $bus = $this->container()->get(MessageBusInterface::class);

        $result = $bus->handle(new BuildSiteCommand(
            source: 'docs',
            output: 'build',
        ));

        static::assertSame(MessageStatus::Success, $result->getStatus());
        static::assertSame([], $result->getResult());
    }

    #[Test]
    public function ordersThePipelineFromThePreEventToThePostEvent(): void
    {
        $pipe = $this->container()->get(MiddlewarePipe::class);

        /** @var SplQueue<MiddlewareInterface> $queue */
        $queue = new ReflectionProperty(MiddlewarePipe::class, 'pipeline')->getValue($pipe);

        $order = [];

        foreach ($queue as $middleware) {
            $order[] = $middleware::class;
        }

        static::assertSame(
            [
                CommandPreHandleMiddleware::class,
                SourceLoadingMiddleware::class,
                MessageHandlerMiddleware::class,
                LayoutWrappingMiddleware::class,
                CommandPostHandleMiddleware::class,
            ],
            $order,
        );
    }

    #[Test]
    public function registersAListenerForEachCommandEvent(): void
    {
        /** @var ListenerProviderInterface $providers */
        $providers = $this->container()->get(ListenerProviderInterface::class);

        $command = new BuildSiteCommand(
            source: 'docs',
            output: 'build',
        );

        $pre = [
            ...$providers->getListenersForEvent(new CommandPreHandleEvent($command)),
        ];
        $post = [
            ...$providers->getListenersForEvent(new CommandPostHandleEvent(new CommandResult(
                command: $command,
                status : MessageStatus::Success,
                result : [],
            ))),
        ];

        static::assertCount(1, $pre);
        static::assertCount(1, $post);
    }

    #[Test]
    public function resolvesBothCustomMiddleware(): void
    {
        static::assertInstanceOf(
            SourceLoadingMiddleware::class,
            $this->container()->get(SourceLoadingMiddleware::class),
        );
        static::assertInstanceOf(
            LayoutWrappingMiddleware::class,
            $this->container()->get(LayoutWrappingMiddleware::class),
        );
    }

    private function container(): ServiceManager
    {
        return require __DIR__ . '/../../config/container.php';
    }
}
