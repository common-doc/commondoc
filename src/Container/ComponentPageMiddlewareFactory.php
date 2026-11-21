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

use CommonDoc\Middleware\ComponentPageMiddleware;
use CommonDoc\Output\DocumentRoute;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the pass that writes component redirects and the home page.
 *
 * @internal
 */
final readonly class ComponentPageMiddlewareFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     *
     * @throws ContainerExceptionInterface When a collaborator cannot be fetched
     * @throws NotFoundExceptionInterface When a collaborator is not registered
     * @throws Type\Exception\ExceptionInterface When a collaborator is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ComponentPageMiddleware {
        return new ComponentPageMiddleware(
            route   : Type\instance_of(DocumentRoute::class)
                ->coerce($container->get(DocumentRoute::class)),
            renderer: Type\instance_of(TemplateRendererInterface::class)
                ->coerce($container->get(TemplateRendererInterface::class)),
        );
    }
}
