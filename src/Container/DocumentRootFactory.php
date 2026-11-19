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
use CommonDoc\SiteConfig;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Serves the site's translation under its own name.
 *
 * The root is the address space of the site, so it belongs to `SiteConfig` and is
 * read from configuration once. Collaborators that only translate a path ask for
 * this rather than for the whole site, which keeps a page factory from depending on
 * the site identity it does not use.
 *
 * @internal
 */
final readonly class DocumentRootFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     *
     * @throws ContainerExceptionInterface When the site config cannot be fetched
     * @throws NotFoundExceptionInterface When the site config is not registered
     * @throws Type\Exception\ExceptionInterface When the service is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): DocumentRoot {
        return Type\instance_of(SiteConfig::class)
            ->coerce($container->get(SiteConfig::class))
            ->documentRoot;
    }
}
