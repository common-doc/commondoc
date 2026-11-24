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

use CommonDoc\View\Helper\ThemeAsset;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Helper\Asset;
use Laminas\View\Helper\BasePath;
use Laminas\View\HelperPluginManager;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the theme asset lookup.
 *
 * Both helpers come from the plugin manager, because that is where they are registered: they
 * are view helpers and have no service of their own. The asset helper is the one holding the
 * theme's merged map, which is why the lookup is asked of it rather than read here.
 *
 * @internal
 */
final readonly class ThemeAssetFactory implements FactoryInterface
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
    ): ThemeAsset {
        $helpers = Type\instance_of(HelperPluginManager::class)
            ->coerce($container->get(HelperPluginManager::class));

        return new ThemeAsset(
            asset   : Type\instance_of(Asset::class)->coerce($helpers->get(Asset::class)),
            basePath: Type\instance_of(BasePath::class)->coerce($helpers->get(BasePath::class)),
        );
    }
}
