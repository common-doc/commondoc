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

use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Mezzio\Helper\UrlHelper;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Serves the route every document URL is generated from.
 *
 * One instance is shared by the provider that registers its pattern, the page factory
 * that generates a page's URL, and the link rewriter that generates a link's, so all three
 * agree on the pattern because there is only one.
 *
 * @internal
 */
final readonly class DocumentRouteFactory implements FactoryInterface
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
    ): DocumentRoute {
        return new DocumentRoute(
            root: Type\instance_of(DocumentRoot::class)
                ->coerce($container->get(DocumentRoot::class)),
            url : Type\instance_of(UrlHelper::class)
                ->coerce($container->get(UrlHelper::class)),
        );
    }
}
