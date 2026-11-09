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

use CommonDoc\CommandHandler\BuildSiteHandler;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\ConverterInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the build site handler.
 *
 * The handler takes the converter and nothing else. This factory is the single
 * place the remaining pipeline collaborators get injected once they exist.
 *
 * @internal
 */
final readonly class BuildSiteHandlerFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): BuildSiteHandler {
        return new BuildSiteHandler($container->get(ConverterInterface::class));
    }
}
