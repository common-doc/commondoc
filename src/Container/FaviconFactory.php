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

use CommonDoc\View\Helper\Favicon;
use CommonDoc\View\Helper\ThemeAsset;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Helper\HeadLink;
use Laminas\View\HelperPluginManager;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the head's favicon link.
 *
 * The lookup is resolved from the plugin manager rather than built here, so the base path and
 * the theme's map reach it the same way they reach the brand: one construction, two callers.
 *
 * @internal
 */
final readonly class FaviconFactory implements FactoryInterface
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
    ): Favicon {
        $helpers = Type\instance_of(HelperPluginManager::class)
            ->coerce($container->get(HelperPluginManager::class));

        return new Favicon(
            themeAsset: Type\instance_of(ThemeAsset::class)->coerce($helpers->get(ThemeAsset::class)),
            headLink  : Type\instance_of(HeadLink::class)->coerce($helpers->get(HeadLink::class)),
        );
    }
}
