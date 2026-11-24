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

use CommonDoc\SiteConfig;
use CommonDoc\View\Helper\Brand;
use CommonDoc\View\Helper\ThemeAsset;
use Laminas\Escaper\EscaperInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Helper\BasePath;
use Laminas\View\HelperPluginManager;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the navbar's brand.
 *
 * The theme asset and base path helpers are resolved from the plugin manager rather than the
 * root container, because that is where they are registered: they are view helpers and have no
 * service of their own. The asset helper carries the theme's map, which is why the logo is
 * asked of it rather than read from configuration here.
 *
 * @internal
 */
final readonly class BrandFactory implements FactoryInterface
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
    ): Brand {
        $helpers = Type\instance_of(HelperPluginManager::class)
            ->coerce($container->get(HelperPluginManager::class));

        return new Brand(
            site      : Type\instance_of(SiteConfig::class)->coerce($container->get(SiteConfig::class)),
            themeAsset: Type\instance_of(ThemeAsset::class)->coerce($helpers->get(ThemeAsset::class)),
            basePath  : Type\instance_of(BasePath::class)->coerce($helpers->get(BasePath::class)),
            escaper   : Type\instance_of(EscaperInterface::class)
                ->coerce($container->get(EscaperInterface::class)),
        );
    }
}
