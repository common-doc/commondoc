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

use Closure;
use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\PageContext;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Mezzio\Router\RouteCollectorInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the build site handler.
 *
 * Every collaborator is fetched and coerced here, so the handler receives types
 * it can rely on and never reaches for a container. The two closures are
 * services in their own right: `ConverterFactoryFactory` makes the per-build
 * converter, and `PageFactoryFactory` makes the per-document page. Both are
 * coerced because a handler without either cannot build anything, so a wrong
 * service should fail here with a typed message instead of at the first document.
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
            converter : Type\instance_of(Closure::class)
                ->coerce($container->get(ConverterFactoryFactory::class)),
            page      : Type\instance_of(Closure::class)
                ->coerce($container->get(PageFactoryFactory::class)),
        );
    }
}
