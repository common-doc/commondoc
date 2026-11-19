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

use CommonDoc\View\HeadingListener;
use CommonDoc\View\PageContext;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the heading listener.
 *
 * It takes the same per-document context the page listener writes to, because a
 * document's sections are read by whoever is building links for that document and the
 * context is already the thing scoped to one conversion.
 *
 * @internal
 */
final readonly class HeadingListenerFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     *
     * @throws ContainerExceptionInterface When the context cannot be fetched
     * @throws NotFoundExceptionInterface When the context is not registered
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): HeadingListener {
        return new HeadingListener($container->get(PageContext::class));
    }
}
