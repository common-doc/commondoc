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
use CommonDoc\Container\ExtensionPluginManager;
use CommonDoc\Input\Root;
use CommonDoc\Listener\CommandPostHandleListener;
use CommonDoc\Listener\CommandPreHandleListener;
use CommonDoc\Middleware\AssetCopyMiddleware;
use CommonDoc\Middleware\ComponentPageMiddleware;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use CommonDoc\Record\BuildRecord;
use FilesystemIterator;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Resolver\AggregateResolver;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\NormalizeHeadings\NormalizeHeadingsExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use Mezzio\LaminasView\ConfigProvider as LaminasViewProvider;
use Phly\EventDispatcher\ConfigProvider as EventDispatcherProvider;
use Phly\EventDispatcher\EventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use SplFileInfo;
use SplQueue;
use Symfony\Component\Console\Application;
use Webware\ConfigManager\ConfigAggregator;
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
use Webware\Theme\ConfigProvider as ThemeProvider;

use function array_map;
use function bin2hex;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

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
#[CoversClass(ExtensionPluginManager::class)]
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
        $tree = $this->temporaryTree();

        file_put_contents(
            filename: "{$tree}/source/index.md",
            data    : "# Home\n",
        );

        try {
            /** @var MessageBusInterface $bus */
            $bus = $this->container()->get(MessageBusInterface::class);

            $result = $bus->handle(new BuildSiteCommand(
                roots : [new Root(path: "{$tree}/source")],
                output: "{$tree}/build",
            ));

            static::assertSame(MessageStatus::Success, $result->getStatus());

            /** @var mixed $raw */
            $raw = $result->getResult();

            static::assertInstanceOf(BuildRecord::class, $raw);
            static::assertSame(['index.md'], $raw->output);
            static::assertFileExists("{$tree}/build/index.html");
        } finally {
            $this->removeDirectory($tree);
        }
    }

    /**
     * config/config.php documents two provider order constraints, and neither survives a
     * provider list that the aggregator does not merge in order.
     *
     * The theme pair is merged here as a pair because that is the constraint the comment
     * states: LaminasViewProvider first, ThemeProvider second, both owning
     * AggregateResolver::class, and the later one winning. The pair is asserted separately
     * from the container because Laminas\View\ConfigProvider also claims that key later in
     * the real list, so the container value is not the theme's.
     */
    #[Test]
    public function keepsTheDocumentedProviderOrderConstraints(): void
    {
        $laminasView = new LaminasViewProvider()();
        $theme       = new ThemeProvider()();

        // Both providers own AggregateResolver::class, so an assertion that only checked the
        // merged value would still pass if they happened to contribute the same factory.
        static::assertNotSame(
            $laminasView['dependencies']['factories'][AggregateResolver::class],
            $theme['dependencies']['factories'][AggregateResolver::class],
        );

        $merged = new ConfigAggregator([LaminasViewProvider::class, ThemeProvider::class])->getMergedConfig();

        static::assertSame(
            $theme['dependencies']['factories'][AggregateResolver::class],
            $merged['dependencies']['factories'][AggregateResolver::class],
        );

        /** @var array<string, mixed> $config */
        $config = $this->container()->get('config');

        // webware-event aliases the dispatcher interface and ships no factory for the target;
        // phly-event-dispatcher supplies that factory, and the alias only resolves while both
        // contributions are merged.
        static::assertSame(
            EventDispatcher::class,
            $config['dependencies']['aliases'][EventDispatcherInterface::class],
        );
        static::assertSame(
            new EventDispatcherProvider()()['dependencies']['factories'][EventDispatcher::class],
            $config['dependencies']['factories'][EventDispatcher::class],
        );
        static::assertInstanceOf(
            EventDispatcher::class,
            $this->container()->get(EventDispatcherInterface::class),
        );
    }

    #[Test]
    public function loadsTheDeclaredExtensionsAfterCore(): void
    {
        $environment = $this->container()->get(EnvironmentInterface::class);

        static::assertInstanceOf(EnvironmentInterface::class, $environment);
        static::assertSame(
            [
                CommonMarkCoreExtension::class,
                TableExtension::class,
                StrikethroughExtension::class,
                TaskListExtension::class,
                HeadingPermalinkExtension::class,
                TableOfContentsExtension::class,
                NormalizeHeadingsExtension::class,
                ExternalLinkExtension::class,
                AutolinkExtension::class,
                DisallowedRawHtmlExtension::class,
            ],
            array_map(
                static fn(ExtensionInterface $extension): string => $extension::class,
                [...$environment->getExtensions()],
            ),
        );
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
                AssetCopyMiddleware::class,
                ComponentPageMiddleware::class,
                MessageHandlerMiddleware::class,
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
            roots : [new Root(path: 'docs')],
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
    public function resolvesTheSourceLoadingMiddleware(): void
    {
        static::assertInstanceOf(
            SourceLoadingMiddleware::class,
            $this->container()->get(SourceLoadingMiddleware::class),
        );
    }

    private function container(): ServiceManager
    {
        return require __DIR__ . '/../../config/container.php';
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());

                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($directory);
    }

    /**
     * A throwaway directory with a source tree inside it.
     *
     * One parent holds both the source and the output, so removing it removes
     * everything the build wrote without a second path to keep in step.
     */
    private function temporaryTree(): string
    {
        $tree = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'commondoc-boot-' . bin2hex(random_bytes(8));

        mkdir("{$tree}/source", recursive: true);

        return $tree;
    }
}
