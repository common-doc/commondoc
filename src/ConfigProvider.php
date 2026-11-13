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

namespace CommonDoc;

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\Console\BuildCommand;
use CommonDoc\Container\BuildCommandFactory;
use CommonDoc\Container\BuildSiteHandlerFactory;
use CommonDoc\Container\CommandPostHandleListenerFactory;
use CommonDoc\Container\CommandPreHandleListenerFactory;
use CommonDoc\Container\EnvironmentFactory;
use CommonDoc\Container\ExtensionPluginManager;
use CommonDoc\Container\ExtensionPluginManagerFactory;
use CommonDoc\Container\LayoutWrappingMiddlewareFactory;
use CommonDoc\Container\MarkdownConverterFactory;
use CommonDoc\Container\PageContextFactory;
use CommonDoc\Container\PageContextListenerFactory;
use CommonDoc\Container\SourceLoadingMiddlewareFactory;
use CommonDoc\Listener\CommandPostHandleListener;
use CommonDoc\Listener\CommandPreHandleListener;
use CommonDoc\Middleware\LayoutWrappingMiddleware;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\MarkdownConverter;
use Webware\Console\ConsoleInterface;
use Webware\Event\ConfigProvider as EventProvider;
use Webware\MessageBus\ConfigProvider as MessageBusConfigProvider;
use Webware\MessageBus\Event\Command\CommandPostHandleEvent;
use Webware\MessageBus\Event\Command\CommandPreHandleEvent;
use Webware\MessageBus\MessageBusInterface;

use function dirname;

/**
 * Wiring entry point for the package.
 *
 * Declared under `extra.laminas.config-provider` in composer.json, so the host
 * config aggregator merges this without any further registration. Contributing
 * `ConsoleInterface::class['commands']` is the whole console integration: the
 * host command loader merges every provider's map, so the build command appears
 * in the menu with no menu wiring of its own.
 *
 * The bus is wired the same way: `command_map` is merged with the map the bus
 * package itself contributes, so the remaining bus keys stay untouched. The
 * listeners ride the `listeners` key the event package owns, which is how the
 * command pre and post handle events reach them without touching the pipeline.
 * That key is spelled out in `ProviderConfig` rather than written as
 * `EventProvider::LISTENER_KEY`, because the analyzer resolves a class-constant
 * shape key against this namespace instead of the class it names.
 *
 * The middleware pipeline merges the same way, and the priorities are the
 * ordering contract: 100 pre-handle event, 50 source loading, 1 message handler,
 * 0 layout wrapping, -100 post-handle event. `SplPriorityQueue` is not stable,
 * so each custom middleware needs a priority of its own rather than a shared one.
 *
 * The extension plugin manager is wired here in both halves: its factory entry
 * and the plugin manager's class-name key. Neither half belongs in an autoload
 * file, because this provider is merged unconditionally through
 * `extra.laminas.config-provider`, so a file that is removed or overridden
 * cannot drop either one.
 *
 * `getExtensions()` returns the fragment and `__invoke()` supplies the top-level
 * key, the same split `getDependencies()` uses for `'dependencies'`. That key is
 * the plugin manager's class name, which is the contract every extension
 * package's ConfigProvider targets, so the aggregator unions the sections
 * instead of one replacing the other. This package contributes the empty
 * section: the extensions it always loads are the plugin manager's own `CONFIG`,
 * not a list here, so no edit to this file can drop them.
 *
 * That split is what fixes the load order. The environment adds
 * `CommonMarkCoreExtension` first, then the plugin manager's always-loaded set,
 * then the names this section declares, so core and the defaults always precede
 * a package's extensions. Within the section the order is the aggregator's, not
 * this file's: `config.php` aggregates the third-party providers before this
 * one, and `ConfigAggregator::mergeArray` keeps first-seen key order while
 * recursing into the shared class-name key.
 *
 * @type CommandMap = array<class-string, class-string>
 * @type MiddlewareSpec = array{middleware: class-string, priority: int}
 * @type MiddlewarePipeline = list<MiddlewareSpec>
 * @type BusConfig = array{command_map: CommandMap, middleware_pipeline: MiddlewarePipeline}
 * @type ListenerConfig = array<class-string, list<class-string>>
 * @type ConsoleConfig = array{commands: array<string, class-string>}
 * @type ExtensionSet = array{
 *   factories?: array<class-string, class-string>,
 *   invokables?: array<class-string, class-string>,
 * }
 * @type Dependencies = array{
 *   aliases: array<interface-string, class-string>,
 *   factories: array<class-string, class-string>,
 * }
 * @type ViewConfig = array{template_path_stack: list<non-empty-string>}
 * @type ProviderConfig = array{
 *   dependencies: Dependencies,
 *   view_manager: ViewConfig,
 *   Webware\Console\ConsoleInterface: ConsoleConfig,
 *   Webware\MessageBus\MessageBusInterface: BusConfig,
 *   listeners: ListenerConfig,
 *   'CommonDoc\Container\ExtensionPluginManager': ExtensionSet,
 * }
 * @internal
 */
final readonly class ConfigProvider
{
    /** @return BusConfig */
    public function getBusConfig(): array
    {
        return [
            MessageBusConfigProvider::COMMAND_MAP_KEY         => [
                BuildSiteCommand::class => BuildSiteHandler::class,
            ],
            MessageBusConfigProvider::MIDDLEWARE_PIPELINE_KEY => [
                [
                    'middleware' => SourceLoadingMiddleware::class,
                    'priority'   => 50,
                ],
                [
                    'middleware' => LayoutWrappingMiddleware::class,
                    'priority'   => 0,
                ],
            ],
        ];
    }

    /** @return ConsoleConfig */
    public function getConsoleConfig(): array
    {
        return [
            'commands' => [
                BuildCommand::NAME => BuildCommand::class,
            ],
        ];
    }

    /** @return Dependencies */
    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                ConverterInterface::class => MarkdownConverter::class,
            ],
            'factories' => [
                BuildCommand::class              => BuildCommandFactory::class,
                BuildSiteHandler::class          => BuildSiteHandlerFactory::class,
                CommandPostHandleListener::class => CommandPostHandleListenerFactory::class,
                CommandPreHandleListener::class  => CommandPreHandleListenerFactory::class,
                EnvironmentInterface::class      => EnvironmentFactory::class,
                ExtensionPluginManager::class    => ExtensionPluginManagerFactory::class,
                LayoutWrappingMiddleware::class  => LayoutWrappingMiddlewareFactory::class,
                MarkdownConverter::class         => MarkdownConverterFactory::class,
                PageContext::class               => PageContextFactory::class,
                PageContextListener::class       => PageContextListenerFactory::class,
                SourceLoadingMiddleware::class   => SourceLoadingMiddlewareFactory::class,
            ],
        ];
    }

    /**
     * The plugin manager section this package contributes.
     *
     * Empty by default, because the extensions this package always loads are
     * `ExtensionPluginManager::CONFIG` and are not declared here. An extension
     * package contributes its own `factories` or `invokables` under the same
     * class-name key, which is the whole registration step for that package.
     *
     * @return ExtensionSet
     */
    public function getExtensions(): array
    {
        return [];
    }

    /** @return ListenerConfig */
    public function getListenerConfig(): array
    {
        return [
            CommandPreHandleEvent::class  => [CommandPreHandleListener::class],
            CommandPostHandleEvent::class => [CommandPostHandleListener::class],
        ];
    }

    /** @return ViewConfig */
    public function getViewConfig(): array
    {
        return [
            'template_path_stack' => [
                dirname(__DIR__) . '/templates',
            ],
        ];
    }

    /** @return ProviderConfig */
    public function __invoke(): array
    {
        return [
            'dependencies'                => $this->getDependencies(),
            'view_manager'                => $this->getViewConfig(),
            ConsoleInterface::class       => $this->getConsoleConfig(),
            MessageBusInterface::class    => $this->getBusConfig(),
            EventProvider::LISTENER_KEY   => $this->getListenerConfig(),
            ExtensionPluginManager::class => $this->getExtensions(),
        ];
    }
}
