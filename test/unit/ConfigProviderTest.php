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
use CommonDoc\Container\AssetCopyMiddlewareFactory;
use CommonDoc\Container\BuildCommandFactory;
use CommonDoc\Container\BuildSiteHandlerFactory;
use CommonDoc\Container\CommandPostHandleListenerFactory;
use CommonDoc\Container\CommandPreHandleListenerFactory;
use CommonDoc\Container\ComponentMenuFactory;
use CommonDoc\Container\ComponentPageMiddlewareFactory;
use CommonDoc\Container\ConverterFactoryFactory;
use CommonDoc\Container\DocumentRootFactory;
use CommonDoc\Container\DocumentRouteFactory;
use CommonDoc\Container\DocumentViewRendererFactory;
use CommonDoc\Container\EnvironmentFactory;
use CommonDoc\Container\ExtensionPluginManager;
use CommonDoc\Container\ExtensionPluginManagerFactory;
use CommonDoc\Container\HeadingListenerFactory;
use CommonDoc\Container\MarkdownConverterFactory;
use CommonDoc\Container\OutputClearingMiddlewareFactory;
use CommonDoc\Container\PageContextFactory;
use CommonDoc\Container\PageContextListenerFactory;
use CommonDoc\Container\PageFactoryFactory;
use CommonDoc\Container\SearchIndexMiddlewareFactory;
use CommonDoc\Container\SiteConfigFactory;
use CommonDoc\Container\SourceLoadingMiddlewareFactory;
use CommonDoc\Container\VersionSwitchFactory;
use CommonDoc\Extension\Admonition\AdmonitionExtension;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Listener\CommandPostHandleListener;
use CommonDoc\Listener\CommandPreHandleListener;
use CommonDoc\Middleware\AssetCopyMiddleware;
use CommonDoc\Middleware\ComponentPageMiddleware;
use CommonDoc\Middleware\OutputClearingMiddleware;
use CommonDoc\Middleware\SearchIndexMiddleware;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\SiteConfig;
use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\HeadingListener;
use CommonDoc\View\Helper\ComponentMenu;
use CommonDoc\View\Helper\VersionSwitch;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\MarkdownConverter;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorFactory;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
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
use Webware\Router\QueryParamRouter;

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
#[CoversMethod(ConfigProvider::class, 'getViewHelpers')]
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
                'view_helpers'                => $provider->getViewHelpers(),
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
    public function pipesTheOutputClearingMiddlewareFirst(): void
    {
        $config = new ConfigProvider()->__invoke();

        static::assertSame(
            [
                [
                    'middleware' => OutputClearingMiddleware::class,
                    'priority'   => 60,
                ],
                [
                    'middleware' => SourceLoadingMiddleware::class,
                    'priority'   => 50,
                ],
                [
                    'middleware' => AssetCopyMiddleware::class,
                    'priority'   => 40,
                ],
                [
                    'middleware' => ComponentPageMiddleware::class,
                    'priority'   => 30,
                ],
                [
                    'middleware' => SearchIndexMiddleware::class,
                    'priority'   => 20,
                ],
            ],
            $config[MessageBusInterface::class][MessageBusConfigProvider::MIDDLEWARE_PIPELINE_KEY],
        );
    }

    #[Test]
    public function pointsTheViewAtTheShippedTemplates(): void
    {
        $config = new ConfigProvider()->getViewConfig();

        static::assertSame(
            ['commondoc::document', 'commondoc::home', 'commondoc::layout', 'commondoc::redirect'],
            array_keys($config['map']),
        );
        static::assertSame('commondoc::layout', $config['layout']);

        // The directory is the theme the resolver falls back to; the name keeps the
        // package name, because that is the namespace a consumer overrides in.
        foreach ($config['map'] as $name => $path) {
            static::assertStringEndsWith('/default/' . substr($name, strlen('commondoc::')) . '.phtml', $path);
            static::assertFileExists($path);
        }
    }

    #[Test]
    public function publishesThePluginManagerKeyWithTheAdmonitionExtension(): void
    {
        $expected = ['invokables' => [AdmonitionExtension::class => AdmonitionExtension::class]];

        static::assertSame($expected, new ConfigProvider()->getExtensions());
        static::assertSame($expected, new ConfigProvider()->__invoke()[ExtensionPluginManager::class]);
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
                ConverterInterface::class      => MarkdownConverter::class,
                RouteCollectorInterface::class => RouteCollector::class,
                RouterInterface::class         => QueryParamRouter::class,
            ],
            $dependencies['aliases'],
        );

        static::assertSame(
            [
                BuildCommand::class              => BuildCommandFactory::class,
                BuildSiteHandler::class          => BuildSiteHandlerFactory::class,
                CommandPostHandleListener::class => CommandPostHandleListenerFactory::class,
                CommandPreHandleListener::class  => CommandPreHandleListenerFactory::class,
                ConverterFactoryFactory::class   => ConverterFactoryFactory::class,
                DocumentRoot::class              => DocumentRootFactory::class,
                DocumentRoute::class             => DocumentRouteFactory::class,
                DocumentViewRenderer::class      => DocumentViewRendererFactory::class,
                EnvironmentInterface::class      => EnvironmentFactory::class,
                ExtensionPluginManager::class    => ExtensionPluginManagerFactory::class,
                HeadingListener::class           => HeadingListenerFactory::class,
                MarkdownConverter::class         => MarkdownConverterFactory::class,
                PageContext::class               => PageContextFactory::class,
                PageContextListener::class       => PageContextListenerFactory::class,
                PageFactoryFactory::class        => PageFactoryFactory::class,
                RouteCollector::class            => RouteCollectorFactory::class,
                SiteConfig::class                => SiteConfigFactory::class,
                AssetCopyMiddleware::class       => AssetCopyMiddlewareFactory::class,
                ComponentPageMiddleware::class   => ComponentPageMiddlewareFactory::class,
                OutputClearingMiddleware::class  => OutputClearingMiddlewareFactory::class,
                SearchIndexMiddleware::class     => SearchIndexMiddlewareFactory::class,
                SourceLoadingMiddleware::class   => SourceLoadingMiddlewareFactory::class,
            ],
            $dependencies['factories'],
        );
    }

    #[Test]
    public function registersTheMiddlewareABuildRouteCarries(): void
    {
        // A route cannot be registered without middleware, and this one has
        // nothing to inject, so it is declared as an invokable rather than
        // through a factory that would only return `new`.
        static::assertSame(
            [NotServedMiddleware::class => NotServedMiddleware::class],
            new ConfigProvider()->getDependencies()['invokables'],
        );
    }

    #[Test]
    public function registersTheNavbarMenusUnderTheirAliases(): void
    {
        $helpers = new ConfigProvider()->getViewHelpers();

        static::assertSame(
            [
                'componentMenu' => ComponentMenu::class,
                'versionSwitch' => VersionSwitch::class,
            ],
            $helpers['aliases'],
        );
        static::assertSame(
            [
                ComponentMenu::class => ComponentMenuFactory::class,
                VersionSwitch::class => VersionSwitchFactory::class,
            ],
            $helpers['factories'],
        );
    }

    #[Test]
    public function resolvesTheCollectorInterfaceToTheCollector(): void
    {
        $aliases = new ConfigProvider()->getDependencies()['aliases'];

        static::assertSame(RouteCollector::class, $aliases[RouteCollectorInterface::class]);
    }

    #[Test]
    public function resolvesTheRouterTheCollectorDependsOn(): void
    {
        // RouteCollectorFactory throws MissingDependencyException without a
        // RouterInterface, and Webware\Router\ConfigProvider ships that alias
        // commented out, so this package has to contribute it or nothing resolves.
        $aliases = new ConfigProvider()->getDependencies()['aliases'];

        static::assertSame(QueryParamRouter::class, $aliases[RouterInterface::class]);
    }
}
