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
use CommonDoc\Container\DocumentViewRendererFactory;
use CommonDoc\Container\EnvironmentFactory;
use CommonDoc\Container\ExtensionPluginManager;
use CommonDoc\Container\ExtensionPluginManagerFactory;
use CommonDoc\Container\MarkdownConverterFactory;
use CommonDoc\Container\PageContextFactory;
use CommonDoc\Container\PageContextListenerFactory;
use CommonDoc\Container\SourceLoadingMiddlewareFactory;
use CommonDoc\Listener\CommandPostHandleListener;
use CommonDoc\Listener\CommandPreHandleListener;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
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

use function array_keys;
use function strlen;
use function substr;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
#[CoversMethod(ConfigProvider::class, 'getBusConfig')]
#[CoversMethod(ConfigProvider::class, 'getConsoleConfig')]
#[CoversMethod(ConfigProvider::class, 'getDependencies')]
#[CoversMethod(ConfigProvider::class, 'getExtensions')]
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
                'dependencies'                => $provider->getDependencies(),
                'templates'                   => $provider->getViewConfig(),
                ConsoleInterface::class       => $provider->getConsoleConfig(),
                MessageBusInterface::class    => $provider->getBusConfig(),
                EventProvider::LISTENER_KEY   => $provider->getListenerConfig(),
                ExtensionPluginManager::class => $provider->getExtensions(),
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
    public function leavesTheDeadPathStackOutOfTheViewConfig(): void
    {
        static::assertArrayNotHasKey('template_path_stack', new ConfigProvider()->getViewConfig());
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
    public function pipesTheSourceLoadingMiddleware(): void
    {
        $config = new ConfigProvider()->__invoke();

        static::assertSame(
            [
                [
                    'middleware' => SourceLoadingMiddleware::class,
                    'priority'   => 50,
                ],
            ],
            $config[MessageBusInterface::class][MessageBusConfigProvider::MIDDLEWARE_PIPELINE_KEY],
        );
    }

    #[Test]
    public function pointsTheViewAtTheShippedTemplates(): void
    {
        $config = new ConfigProvider()->getViewConfig();

        static::assertSame(['commondoc::document', 'commondoc::layout'], array_keys($config['map']));
        static::assertSame('commondoc::layout', $config['layout']);

        foreach ($config['map'] as $name => $path) {
            static::assertStringEndsWith('/commondoc/' . substr($name, strlen('commondoc::')) . '.phtml', $path);
            static::assertFileExists($path);
        }
    }

    #[Test]
    public function publishesThePluginManagerKeyWithAnEmptyFragment(): void
    {
        static::assertSame([], new ConfigProvider()->getExtensions());
        static::assertSame([], new ConfigProvider()->__invoke()[ExtensionPluginManager::class]);
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
                DocumentViewRenderer::class      => DocumentViewRendererFactory::class,
                EnvironmentInterface::class      => EnvironmentFactory::class,
                ExtensionPluginManager::class    => ExtensionPluginManagerFactory::class,
                MarkdownConverter::class         => MarkdownConverterFactory::class,
                PageContext::class               => PageContextFactory::class,
                PageContextListener::class       => PageContextListenerFactory::class,
                SourceLoadingMiddleware::class   => SourceLoadingMiddlewareFactory::class,
            ],
            $dependencies['factories'],
        );
    }
}
