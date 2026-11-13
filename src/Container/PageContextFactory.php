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

use CommonDoc\View\PageContext;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Construction seam for the page context.
 *
 * The service manager shares a factory result by default, so the listener and
 * the caller that sets the page resolve the same slot.
 *
 * @internal
 */
final readonly class PageContextFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): PageContext {
        return new PageContext();
    }
}
