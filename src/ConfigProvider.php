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
use CommonDoc\Container\SourceLoadingMiddlewareFactory;
use CommonDoc\Listener\CommandPostHandleListener;
use CommonDoc\Listener\CommandPreHandleListener;
use CommonDoc\Middleware\LayoutWrappingMiddleware;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\NormalizeHeadings\NormalizeHeadingsExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
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
 * and the extension set this package ships. Neither half belongs in an autoload
 * file, because this provider is merged unconditionally through
 * `extra.laminas.config-provider`, so a file that is removed or overridden
 * cannot drop the manager or the extensions it loads. An extension package
 * still contributes its own entries under the plugin manager's class-name key,
 * which is what lets it gain features on its own release cycle without a change
 * here.
 *
 * The resulting order is the aggregator's, not this file's. In `config.php` the
 * third-party providers are aggregated before this one, and
 * `ConfigAggregator::mergeArray` keeps first-seen key order while recursing into
 * the shared class-name key, so a third-party extension lands ahead of the nine
 * declared in `getExtensionConfig()`. The environment still adds
 * `CommonMarkCoreExtension` ahead of every one of them.
 *
 * @type CommandMap = array<class-string, class-string>
 * @type MiddlewareSpec = array{middleware: class-string, priority: int}
 * @type MiddlewarePipeline = list<MiddlewareSpec>
 * @type BusConfig = array{command_map: CommandMap, middleware_pipeline: MiddlewarePipeline}
 * @type ListenerConfig = array<class-string, list<class-string>>
 * @type ConsoleConfig = array{commands: array<string, class-string>}
 * @type ExtensionSet = array{invokables: array<class-string, class-string>}
 * @type ExtensionConfig = array{'CommonDoc\Container\ExtensionPluginManager': ExtensionSet}
 * @type Dependencies = array{
 *   aliases: array<class-string, class-string>,
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
                SourceLoadingMiddleware::class   => SourceLoadingMiddlewareFactory::class,
            ],
        ];
    }

    /**
     * The extension set this package ships.
     *
     * Keyed by the plugin manager's own class name, which is the contract every
     * extension package's ConfigProvider targets, so the aggregator unions the
     * two sections instead of one replacing the other.
     *
     * Every entry is invokable, so the name is the whole registration. An
     * extension that needs config or a collaborator contributes a factory under
     * the same key instead and reads `$container->get('config')`.
     *
     * Do not add AttributesExtension, SmartPunctExtension, EmbedExtension,
     * MentionExtension, HighlightExtension, InlinesOnlyExtension or
     * GithubFlavoredMarkdownExtension: those are out of scope for this package.
     *
     * @return ExtensionConfig
     */
    public function getExtensionConfig(): array
    {
        return [
            ExtensionPluginManager::class => [
                'invokables' => [
                    TableExtension::class             => TableExtension::class,
                    StrikethroughExtension::class     => StrikethroughExtension::class,
                    TaskListExtension::class          => TaskListExtension::class,
                    HeadingPermalinkExtension::class  => HeadingPermalinkExtension::class,
                    TableOfContentsExtension::class   => TableOfContentsExtension::class,
                    NormalizeHeadingsExtension::class => NormalizeHeadingsExtension::class,
                    ExternalLinkExtension::class      => ExternalLinkExtension::class,
                    AutolinkExtension::class          => AutolinkExtension::class,
                    DisallowedRawHtmlExtension::class => DisallowedRawHtmlExtension::class,
                ],
            ],
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
            'dependencies'              => $this->getDependencies(),
            'view_manager'              => $this->getViewConfig(),
            ConsoleInterface::class     => $this->getConsoleConfig(),
            MessageBusInterface::class  => $this->getBusConfig(),
            EventProvider::LISTENER_KEY => $this->getListenerConfig(),
            ...$this->getExtensionConfig(),
        ];
    }
}
