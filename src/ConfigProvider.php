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
use CommonDoc\Middleware\SearchIndexMiddleware;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
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
use Webware\Console\ConsoleInterface;
use Webware\Event\ConfigProvider as EventProvider;
use Webware\MessageBus\ConfigProvider as MessageBusConfigProvider;
use Webware\MessageBus\Event\Command\CommandPostHandleEvent;
use Webware\MessageBus\Event\Command\CommandPreHandleEvent;
use Webware\MessageBus\MessageBusInterface;
use Webware\Router\QueryParamRouter;

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
 * -100 post-handle event. `SplPriorityQueue` is not stable, so the one custom
 * middleware carries a priority of its own rather than a shared one.
 *
 * The router is wired here in the two halves the build needs. `RouterInterface`
 * aliases to `QueryParamRouter`, because `Webware\Router\ConfigProvider`
 * registers that factory but ships its own alias commented out, leaving the
 * interface unresolved. `RouteCollector` gets `RouteCollectorFactory`, which
 * needs nothing beyond that alias.
 *
 * `Mezzio\Router\ConfigProvider` is deliberately not aggregated. It registers
 * the same two entries and then four dispatch middleware factories, and those
 * need `Mezzio\MiddlewareFactoryInterface`, which arrives only with
 * `mezzio/mezzio`. This package has no HTTP pipeline and never resolves them, so
 * aggregating it would register services it cannot build in exchange for saving
 * two lines.
 *
 * A build dispatches nothing. Routes exist so navigation and `generateUri()`
 * render in templates exactly as they would under a request, which is why the
 * collector has to be resolvable at all.
 *
 * The view configuration is contributed here rather than in an autoload file for
 * the same reason: a host that overrides a local file still aggregates this
 * provider, so the body and layout names always resolve. Both are map
 * entries, which is the override seam a site uses when it wants a different body
 * without a code change: adding the same name to its own `templates.map`
 * replaces the entry, and the map outranks the path stack.
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
 * one, and `Webware\ConfigManager\Merge\ConfigMerger::merge`, which the
 * aggregator applies to every contribution, keeps first-seen key order while
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
 *   invokables: array<class-string, class-string>,
 * }
 * @type TemplateMap = array<non-empty-string, non-empty-string>
 * @type ViewConfig = array{map: TemplateMap, layout: non-empty-string}
 * @type ViewHelperConfig = array{
 *   aliases: array<non-empty-string, class-string>,
 *   factories: array<class-string, class-string>,
 * }
 * @type ProviderConfig = array{
 *   dependencies: Dependencies,
 *   templates: ViewConfig,
 *   view_helpers: ViewHelperConfig,
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
                // The scan first: it answers which files exist, and every phase
                // below it reads that answer rather than walking the tree itself.
                [
                    'middleware' => SourceLoadingMiddleware::class,
                    'priority'   => 50,
                ],
                // Then the assets, so every copied file is in place before the
                // first document is rendered and a copy failure is recorded before
                // any conversion is attempted. Copying needs no environment, so it
                // does not belong in the pass that drives CommonMark.
                [
                    'middleware' => AssetCopyMiddleware::class,
                    'priority'   => 40,
                ],
                // Writes the pages that belong to no document, after the handler has
                // registered the routes their URLs are generated from.
                [
                    'middleware' => ComponentPageMiddleware::class,
                    'priority'   => 30,
                ],
                // Reads the pages the handler wrote, so it has to wrap the handler too.
                [
                    'middleware' => SearchIndexMiddleware::class,
                    'priority'   => 20,
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
            'aliases'    => [
                ConverterInterface::class      => MarkdownConverter::class,
                RouteCollectorInterface::class => RouteCollector::class,
                RouterInterface::class         => QueryParamRouter::class,
            ],
            'factories'  => [
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
                SearchIndexMiddleware::class     => SearchIndexMiddlewareFactory::class,
                SourceLoadingMiddleware::class   => SourceLoadingMiddlewareFactory::class,
            ],
            'invokables' => [
                // A build route needs middleware to exist and is never served,
                // so the only honest implementation takes nothing to build.
                NotServedMiddleware::class => NotServedMiddleware::class,
            ],
        ];
    }

    /**
     * The plugin manager section this package contributes: the admonition extension, which is
     * this package's own and so is registered the way any extension package would register
     * itself, through the manager's section rather than by being named in the manager.
     *
     * The extensions this package always loads are `ExtensionPluginManager::CONFIG` and are not
     * declared here. An extension package contributes its own `factories` or `invokables` under
     * the same class-name key, which is the whole registration step for that package.
     *
     * @return ExtensionSet
     */
    public function getExtensions(): array
    {
        return [
            'invokables' => [AdmonitionExtension::class => AdmonitionExtension::class],
        ];
    }

    /** @return ListenerConfig */
    public function getListenerConfig(): array
    {
        return [
            CommandPreHandleEvent::class  => [CommandPreHandleListener::class],
            CommandPostHandleEvent::class => [CommandPostHandleListener::class],
        ];
    }

    /**
     * The templates a site renders with when it supplies none of its own.
     *
     * `default` is the directory name because it is also the theme name the resolver
     * falls back to, so a site's own theme directory is a sibling of this one rather
     * than a second naming convention. The names carry the package name instead,
     * because that is the namespace a consumer overrides in, whatever theme is active.
     *
     * @return ViewConfig
     */
    public function getViewConfig(): array
    {
        return [
            'map'    => [
                'commondoc::document' => dirname(__DIR__) . '/templates/default/document.phtml',
                'commondoc::home'     => dirname(__DIR__) . '/templates/default/home.phtml',
                'commondoc::layout'   => dirname(__DIR__) . '/templates/default/layout.phtml',
                'commondoc::redirect' => dirname(__DIR__) . '/templates/default/redirect.phtml',
            ],
            'layout' => 'commondoc::layout',
        ];
    }

    /**
     * The view helpers this package registers.
     *
     * A helper is registered by class name and given a short alias, because a template
     * calls it by alias while a site overrides it by class name in `view_helpers`.
     *
     * @return ViewHelperConfig
     */
    public function getViewHelpers(): array
    {
        return [
            'aliases'   => [
                'componentMenu' => ComponentMenu::class,
                'versionSwitch' => VersionSwitch::class,
            ],
            'factories' => [
                ComponentMenu::class => ComponentMenuFactory::class,
                VersionSwitch::class => VersionSwitchFactory::class,
            ],
        ];
    }

    /** @return ProviderConfig */
    public function __invoke(): array
    {
        return [
            'dependencies'                => $this->getDependencies(),
            'templates'                   => $this->getViewConfig(),
            'view_helpers'                => $this->getViewHelpers(),
            ConsoleInterface::class       => $this->getConsoleConfig(),
            MessageBusInterface::class    => $this->getBusConfig(),
            EventProvider::LISTENER_KEY   => $this->getListenerConfig(),
            ExtensionPluginManager::class => $this->getExtensions(),
        ];
    }
}
