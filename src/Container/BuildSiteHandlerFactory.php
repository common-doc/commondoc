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
use CommonDoc\Input\Source;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\Output\RouteRegistrar;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\ConverterInterface;
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
 * one, and is never given one. A converter and a page are built per build and per
 * document, so they cannot be injected as instances; what is injected is the
 * function that builds each, which `ConverterFactoryFactory` and `PageFactoryFactory`
 * produce with their own collaborators already resolved.
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
        /** @var Closure(list<non-empty-string> $documents, list<non-empty-string> $assets): ConverterInterface $converters */
        $converters = Type\instance_of(Closure::class)
            ->coerce($container->get(ConverterFactoryFactory::class));

        /** @var Closure(non-empty-string $localPath, string $label, ?Source $source, ?string $repositoryPath): Page $pages */
        $pages = Type\instance_of(Closure::class)
            ->coerce($container->get(PageFactoryFactory::class));

        $route = Type\instance_of(DocumentRoute::class)
            ->coerce($container->get(DocumentRoute::class));

        return new BuildSiteHandler(
            routes    : new RouteRegistrar(
                route     : $route,
                collector : Type\instance_of(RouteCollectorInterface::class)
                    ->coerce($container->get(RouteCollectorInterface::class)),
                middleware: Type\instance_of(NotServedMiddleware::class)
                    ->coerce($container->get(NotServedMiddleware::class)),
            ),
            route     : $route,
            context   : Type\instance_of(PageContext::class)
                ->coerce($container->get(PageContext::class)),
            converters: $converters,
            pages     : $pages,
        );
    }
}
