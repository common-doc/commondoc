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
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Exception\AlreadyInitializedException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtensionInterface;
use Override;
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
use function strval;

/**
 * Construction seam for the Markdown environment.
 *
 * The environment is configured from the aggregated config's environment
 * section, so the option surface lives in config/autoload/commonmark.global.php
 * rather than in this class.
 *
 * Core is added first because it is the non-negotiable baseline. Next come the
 * plugin manager's always-loaded extensions, then the names the plugin manager's
 * config section declares, read in the order that section declares them: its own
 * keys are walked as written, so an entry under `factories` and an entry under
 * `invokables` keep the relative order they were declared in rather than being
 * grouped one key after the other. Any other key in the section declares no
 * extension and is skipped.
 *
 * @internal
 */
final readonly class EnvironmentFactory implements FactoryInterface
{
    /**
     * The config keys that declare an extension.
     *
     * A map rather than a list so array_intersect_key can select them.
     */
    private const array EXTENSION_KEYS = ['factories' => true, 'invokables' => true];

    /**
     * @param array<array-key, mixed> $declared
     * @return list<string>
     */
    private static function namesIn(array $declared): array
    {
        return array_map(strval(...), array_keys($declared));
    }

    /**
     * The extension names the section declares, in the order it declares them.
     *
     * @return list<string>
     */
    private function extensionNames(mixed $config): array
    {
        // The intersection keeps the section's own key order, so a factories
        // entry and an invokables entry stay in the order they were written
        // instead of being grouped by key. array_values then flattens the
        // selected sections back into that single order.
        return array_merge(
            [],
            ...array_values(array_map(
                self::namesIn(...),
                array_filter(
                    array_intersect_key($this->getConfig($config), self::EXTENSION_KEYS),
                    is_array(...),
                ),
            )),
        );
    }

    /**
     * The plugin manager section, or an empty array when it declares nothing.
     *
     * @return array<string, mixed>
     */
    private function getConfig(mixed $config): array
    {
        if (! is_array($config) || ! is_array($config[ExtensionPluginManager::class] ?? null)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $config[ExtensionPluginManager::class];
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsFrom(mixed $config): array
    {
        if (! is_array($config) || ! is_array($config[EnvironmentInterface::class] ?? null)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $config[EnvironmentInterface::class];
    }

    /**
     * @param ?array<array-key, mixed> $options
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws AlreadyInitializedException
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): EnvironmentInterface {
        $environment = new Environment($this->optionsFrom($container->get('config')));
        $environment->addExtension(new CommonMarkCoreExtension());

        $manager = $container->get(ExtensionPluginManager::class);

        $names = [
            ...$manager->getDefaultExtensions(),
            ...$this->extensionNames($container->get('config')),
        ];

        foreach ($names as $name) {
            /** @var ExtensionInterface $extension */
            $extension = $manager->get($name);

            $environment->addExtension($extension);
        }

        return $environment;
    }
}
