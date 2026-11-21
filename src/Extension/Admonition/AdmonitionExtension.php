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

namespace CommonDoc\Extension\Admonition;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Exception\AlreadyInitializedException;
use League\CommonMark\Extension\ExtensionInterface;
use Override;

/**
 * Callouts written as GitHub alerts: `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`,
 * `> [!WARNING]` and `> [!CAUTION]`.
 *
 * `league/commonmark` ships no admonition extension, so this is a node, a listener that finds
 * them in the parsed tree and a renderer for the markup the default theme already styles.
 * It has no configuration and no collaborators, so it is registered as an invokable.
 *
 * @api
 */
final readonly class AdmonitionExtension implements ExtensionInterface
{
    /**
     * @throws AlreadyInitializedException When the environment has already been used
     */
    #[Override]
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addRenderer(Admonition::class, new AdmonitionRenderer())
            ->addEventListener(DocumentParsedEvent::class, new AdmonitionListener());
    }
}
