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
    private const array CONFIG = [
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
    ];

    protected string $instanceOf = ExtensionInterface::class;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        ContainerInterface $creationContext,
        array $config = [],
    ) {
        /** @var ServiceManagerConfiguration $merged */
        $merged = array_replace_recursive(self::CONFIG, $config);

        parent::__construct($creationContext, $merged);
    }

    /**
     * The always-loaded extensions, in the order CONFIG declares them.
     *
     * The environment adds these ahead of anything an extension package
     * contributes, so they are present even when no package is installed.
     *
     * @return list<class-string<ExtensionInterface>>
     */
    public function getDefaultExtensions(): array
    {
        /** @var list<class-string<ExtensionInterface>> */
        return array_keys(self::CONFIG['invokables']);
    }
}
