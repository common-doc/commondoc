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

/*
 * The extension set this installation loads.
 *
 * Keyed by the plugin manager's own class name, which is the contract every
 * extension package's ConfigProvider targets. Installing the package adds it
 * through config aggregation, so this file is never edited to add an extension
 * and an extension package can follow its own release cycle.
 *
 * Every entry here is invokable, so the name is the whole registration. An
 * extension that needs config or a collaborator contributes a factory under the
 * same key instead and reads `$container->get('config')`.
 *
 * Do not add AttributesExtension, SmartPunctExtension, EmbedExtension,
 * MentionExtension, HighlightExtension, InlinesOnlyExtension or
 * GithubFlavoredMarkdownExtension: those are out of scope for this package.
 */

use CommonDoc\Container\ExtensionPluginManager;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\NormalizeHeadings\NormalizeHeadingsExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;

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
