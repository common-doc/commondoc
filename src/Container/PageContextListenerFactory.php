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
use CommonDoc\View\PageContextListener;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Construction seam for the page context listener.
 *
 * @internal
 */
final readonly class PageContextListenerFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): PageContextListener {
        return new PageContextListener($container->get(PageContext::class));
    }
}
