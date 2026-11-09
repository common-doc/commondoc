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

use CommonDoc\Console\BuildCommand;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\MessageBus\MessageBusInterface;

/**
 * Construction seam for the build command.
 *
 * The command takes the bus and nothing else. Building it here keeps the
 * collaborator out of the command's own construction, so the command never has
 * to reach for a container.
 *
 * @internal
 */
final readonly class BuildCommandFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): BuildCommand {
        return new BuildCommand($container->get(MessageBusInterface::class));
    }
}
