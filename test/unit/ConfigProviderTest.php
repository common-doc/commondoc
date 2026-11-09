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
use CommonDoc\ConfigProvider;
use CommonDoc\Console\BuildCommand;
use CommonDoc\Container\BuildCommandFactory;
use CommonDoc\Container\BuildSiteHandlerFactory;
use CommonDoc\Container\CommandPostHandleListenerFactory;
use CommonDoc\Container\CommandPreHandleListenerFactory;
use CommonDoc\Container\EnvironmentFactory;
use CommonDoc\Container\LayoutWrappingMiddlewareFactory;
use CommonDoc\Container\MarkdownConverterFactory;
use CommonDoc\Container\SourceLoadingMiddlewareFactory;
use CommonDoc\Listener\CommandPostHandleListener;
use CommonDoc\Listener\CommandPreHandleListener;
use CommonDoc\Middleware\LayoutWrappingMiddleware;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Console\ConsoleInterface;
use Webware\Event\ConfigProvider as EventProvider;
use Webware\MessageBus\ConfigProvider as MessageBusConfigProvider;
use Webware\MessageBus\Event\Command\CommandPostHandleEvent;
use Webware\MessageBus\Event\Command\CommandPreHandleEvent;
use Webware\MessageBus\MessageBusInterface;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
#[CoversMethod(ConfigProvider::class, 'getBusConfig')]
#[CoversMethod(ConfigProvider::class, 'getConsoleConfig')]
#[CoversMethod(ConfigProvider::class, 'getDependencies')]
#[CoversMethod(ConfigProvider::class, 'getListenerConfig')]
#[CoversMethod(ConfigProvider::class, 'getViewConfig')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function aggregatesEverySection(): void
    {
        $provider = new ConfigProvider();

        static::assertSame(
            [
                'dependencies'              => $provider->getDependencies(),
                'view_manager'              => $provider->getViewConfig(),
                ConsoleInterface::class     => $provider->getConsoleConfig(),
                MessageBusInterface::class  => $provider->getBusConfig(),
                EventProvider::LISTENER_KEY => $provider->getListenerConfig(),
            ],
            $provider->__invoke(),
        );
    }

    #[Test]
    public function contributesTheBuildCommandToTheConsole(): void
    {
        static::assertSame(
            [
                'commands' => [
                    BuildCommand::NAME => BuildCommand::class,
                ],
            ],
            new ConfigProvider()->getConsoleConfig(),
        );
    }

    #[Test]
    public function mapsTheBuildSiteCommandToItsHandler(): void
    {
        static::assertSame(
            [
                BuildSiteCommand::class => BuildSiteHandler::class,
            ],
            new ConfigProvider()->getBusConfig()[MessageBusConfigProvider::COMMAND_MAP_KEY],
        );
    }

    #[Test]
    public function pipesTheCustomMiddlewareAtDistinctPriorities(): void
    {
        $config = new ConfigProvider()->__invoke();

        static::assertSame(
            [
                [
                    'middleware' => SourceLoadingMiddleware::class,
                    'priority'   => 50,
                ],
                [
                    'middleware' => LayoutWrappingMiddleware::class,
                    'priority'   => 0,
                ],
            ],
            $config[MessageBusInterface::class][MessageBusConfigProvider::MIDDLEWARE_PIPELINE_KEY],
        );
    }

    #[Test]
    public function pointsTheViewAtTheShippedTemplates(): void
    {
        $config = new ConfigProvider()->getViewConfig();

        static::assertCount(1, $config['template_path_stack']);

        $root = $config['template_path_stack'][0];

        static::assertDirectoryExists($root);
        static::assertFileExists("{$root}/commondoc/document.phtml");
    }

    #[Test]
    public function registersAListenerForEachCommandEvent(): void
    {
        static::assertSame(
            [
                CommandPreHandleEvent::class  => [CommandPreHandleListener::class],
                CommandPostHandleEvent::class => [CommandPostHandleListener::class],
            ],
            new ConfigProvider()->getListenerConfig(),
        );
    }

    #[Test]
    public function registersTheCommandsTheHandlersTheMiddlewareAndTheConverter(): void
    {
        $dependencies = new ConfigProvider()->getDependencies();

        static::assertSame(
            [
                ConverterInterface::class => MarkdownConverter::class,
            ],
            $dependencies['aliases'],
        );

        static::assertSame(
            [
                BuildCommand::class              => BuildCommandFactory::class,
                BuildSiteHandler::class          => BuildSiteHandlerFactory::class,
                CommandPostHandleListener::class => CommandPostHandleListenerFactory::class,
                CommandPreHandleListener::class  => CommandPreHandleListenerFactory::class,
                EnvironmentInterface::class      => EnvironmentFactory::class,
                LayoutWrappingMiddleware::class  => LayoutWrappingMiddlewareFactory::class,
                MarkdownConverter::class         => MarkdownConverterFactory::class,
                SourceLoadingMiddleware::class   => SourceLoadingMiddlewareFactory::class,
            ],
            $dependencies['factories'],
        );
    }
}
