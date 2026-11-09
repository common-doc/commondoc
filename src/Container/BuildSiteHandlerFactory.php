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
use Override;
use Psr\Container\ContainerInterface;

/**
 * Construction seam for the build site handler.
 *
 * The handler takes no collaborators yet. This factory is the single place the
 * pipeline collaborators get injected once they exist, so adding the converter
 * here changes nothing else.
 *
 * @internal
 */
final readonly class BuildSiteHandlerFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): BuildSiteHandler {
        return new BuildSiteHandler();
    }
}
