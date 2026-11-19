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
use CommonDoc\SiteConfig;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\MessageBus\MessageBusInterface;

/**
 * Construction seam for the build command.
 *
 * The command takes the bus and the site, and nothing else. The site is what lets a
 * run with no arguments build the configured site; the two command-line options are
 * overrides applied on top of it rather than the source of the configuration.
 *
 * Building it here keeps both collaborators out of the command's own construction,
 * so the command never has to reach for a container.
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
     * @throws Type\Exception\ExceptionInterface When a collaborator is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): BuildCommand {
        return new BuildCommand(
            bus : Type\instance_of(MessageBusInterface::class)
                ->coerce($container->get(MessageBusInterface::class)),
            site: Type\instance_of(SiteConfig::class)
                ->coerce($container->get(SiteConfig::class)),
        );
    }
}
