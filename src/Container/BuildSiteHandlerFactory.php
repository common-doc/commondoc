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

use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\PageContext;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Mezzio\Router\RouteCollectorInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the build site handler.
 *
 * Every injected collaborator is fetched and coerced here, so the handler
 * receives types it can rely on and never reaches for a container to resolve
 * one. The locator it does receive is not for resolving services: a converter
 * and a page are built per build and per document, so they cannot be injected
 * as instances, and `build()` on this locator is how the handler asks for one
 * without the service manager caching it.
 *
 * @internal
 */
final readonly class BuildSiteHandlerFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Type\Exception\ExceptionInterface When a collaborator is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): BuildSiteHandler {
        return new BuildSiteHandler(
            root      : Type\instance_of(DocumentRoot::class)
                ->coerce($container->get(DocumentRoot::class)),
            collector : Type\instance_of(RouteCollectorInterface::class)
                ->coerce($container->get(RouteCollectorInterface::class)),
            context   : Type\instance_of(PageContext::class)
                ->coerce($container->get(PageContext::class)),
            middleware: Type\instance_of(NotServedMiddleware::class)
                ->coerce($container->get(NotServedMiddleware::class)),
            services  : Type\instance_of(ServiceLocatorInterface::class)
                ->coerce($container),
        );
    }
}
