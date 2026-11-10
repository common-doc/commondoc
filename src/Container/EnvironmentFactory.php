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
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_array;

/**
 * Construction seam for the Markdown environment.
 *
 * The environment is configured from the aggregated config's environment
 * section, so the option surface lives in config/autoload/commonmark.global.php
 * rather than in this class.
 *
 * Core is added first because it is the non-negotiable baseline; every extension
 * the plugin manager holds then lands after it in declared order.
 *
 * @internal
 */
final readonly class EnvironmentFactory implements FactoryInterface
{
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

        foreach ($container->get(ExtensionPluginManager::class)->extensions() as $extension) {
            $environment->addExtension($extension);
        }

        return $environment;
    }
}
