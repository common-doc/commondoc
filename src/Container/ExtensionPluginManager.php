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

namespace CommonDoc\Container;

use Laminas\ServiceManager\AbstractSingleInstancePluginManager;
use Laminas\ServiceManager\ServiceManager;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\NormalizeHeadings\NormalizeHeadingsExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use Psr\Container\ContainerInterface;

use function array_combine;
use function array_keys;
use function array_replace_recursive;

/**
 * Plugin manager for the CommonMark extensions the environment loads.
 *
 * A plugin manager rather than a list of class names, because it is the creation
 * context for its own factories: an extension that needs config or a collaborator
 * ships a factory that reads `$container->get('config')`, and installing the
 * package is the whole registration step.
 *
 * Single instance is the correct base: CommonMark expects one extension object
 * per name, and extensions hold no per-document state.
 *
 * The config section is keyed by this class name, so every extension package
 * contributes to the same section and this package never names an extension.
 * Which names to load, and in what order, is the consumer's reading of that
 * section; this class only builds and validates them.
 *
 * @extends AbstractSingleInstancePluginManager<ExtensionInterface>
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @internal
 */
final class ExtensionPluginManager extends AbstractSingleInstancePluginManager
{
    /**
     * The extensions that load whether or not any package registers one.
     *
     * A list because the declaration order is the contract. The constructor
     * combines it into an `invokables` map, because laminas requires the id to
     * equal the class name.
     *
     * @var list<class-string<ExtensionInterface>>
     */
    private const array CONFIG = [
        TableExtension::class,
        StrikethroughExtension::class,
        TaskListExtension::class,
        HeadingPermalinkExtension::class,
        TableOfContentsExtension::class,
        NormalizeHeadingsExtension::class,
        ExternalLinkExtension::class,
        AutolinkExtension::class,
        DisallowedRawHtmlExtension::class,
    ];

    /**
     * The config the manager was constructed with, our starting list merged in.
     *
     * @var ServiceManagerConfiguration
     */
    private array $merged;

    protected string $instanceOf = ExtensionInterface::class;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        ContainerInterface $creationContext,
        array $config = [],
    ) {
        /** @var ServiceManagerConfiguration $merged */
        $merged = array_replace_recursive(
            ['invokables' => array_combine(self::CONFIG, self::CONFIG)],
            $config,
        );

        $this->merged = $merged;

        parent::__construct($creationContext, $this->merged);
    }

    /**
     * Every extension this manager will serve, in load order.
     *
     * The starting list comes first, then whatever the application's section
     * declares, because the constructor merges our list into the config it is
     * handed.
     *
     * @return list<string>
     */
    public function getDefaultExtensions(): array
    {
        /** @var array<string, mixed> $factories */
        $factories = $this->merged['factories'] ?? [];

        return [...array_keys($this->merged['invokables']), ...array_keys($factories)];
    }
}
