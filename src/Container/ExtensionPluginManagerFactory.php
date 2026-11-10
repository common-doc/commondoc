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

use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

use function is_array;

/**
 * Construction seam for the extension plugin manager.
 *
 * The section is keyed by the plugin manager's own class name, so an extension
 * package's ConfigProvider lands in the same place as every other one and this
 * package never names an extension. A missing section yields an empty manager
 * rather than an error, so an installation with no extension package still
 * renders core Markdown.
 *
 * @internal
 */
final readonly class ExtensionPluginManagerFactory implements FactoryInterface
{
    /**
     * @return array<string, mixed>
     */
    private static function getConfig(mixed $config): array
    {
        if (! is_array($config) || ! is_array($config[ExtensionPluginManager::class] ?? null)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $config[ExtensionPluginManager::class];
    }

    /**
     * @param ?array<array-key, mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ExtensionPluginManager {
        return new ExtensionPluginManager($container, self::getConfig($container->get('config')));
    }
}
