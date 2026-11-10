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

use Laminas\ServiceManager\AbstractSingleInstancePluginManager;
use Laminas\ServiceManager\ServiceManager;
use League\CommonMark\Extension\ExtensionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function array_filter;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function is_array;

/**
 * Plugin manager for the CommonMark extensions the environment loads.
 *
 * A plugin manager rather than a list of class names, because it is the creation
 * context for its own factories: an extension that needs config or a collaborator
 * ships a factory that reads `$container->get('config')`, and installing the
 * package is the whole registration step.
 *
 * Single instance is the correct base: CommonMark expects one extension object
 * per name, and extensions hold no per-document state.
 *
 * The config section is keyed by this class name, so every extension package
 * contributes to the same section and this package never names an extension.
 *
 * @extends AbstractSingleInstancePluginManager<ExtensionInterface>
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @internal
 */
final class ExtensionPluginManager extends AbstractSingleInstancePluginManager
{
    /**
     * The config keys that declare an extension.
     *
     * A map rather than a list so array_intersect_key can select them, and so
     * the selection keeps the order the config declared them in.
     */
    private const array EXTENSION_KEYS = ['factories' => true, 'invokables' => true];

    protected string $instanceOf = ExtensionInterface::class;

    /**
     * The extension names in declaration order.
     *
     * @var list<string>
     */
    private array $names = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(ContainerInterface $creationContext, array $config = [])
    {
        $this->names = self::namesFrom($config);

        /** @var ServiceManagerConfiguration $config */
        parent::__construct($creationContext, $config);
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private static function namesFrom(array $config): array
    {
        // The intersection keeps the config's own key order, and array_values
        // flattens the sections back into the single order they were declared in.
        return array_merge(
            [],
            ...array_values(array_map(
                self::namesIn(...),
                array_filter(
                    array_intersect_key($config, self::EXTENSION_KEYS),
                    is_array(...),
                ),
            )),
        );
    }

    /**
     * @param array<array-key, mixed> $services
     * @return list<string>
     */
    private static function namesIn(array $services): array
    {
        return array_map(strval(...), array_keys($services));
    }

    /**
     * The extensions to load, in the order the config declared them.
     *
     * @return list<ExtensionInterface>
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function extensions(): array
    {
        return array_map($this->extensionNamed(...), $this->names);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function extensionNamed(string $name): ExtensionInterface
    {
        /** @var ExtensionInterface */
        return $this->get($name);
    }
}
