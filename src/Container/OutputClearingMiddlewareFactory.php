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

use CommonDoc\Middleware\OutputClearingMiddleware;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Construction seam for the output clearing phase.
 *
 * The middleware takes no collaborators: the output directory and the sources it must not
 * hold are on the message it is handed. This factory exists so the pipeline can resolve it
 * by name and so a collaborator, a logger first among them, has one place to be injected.
 *
 * @internal
 */
final readonly class OutputClearingMiddlewareFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): OutputClearingMiddleware {
        return new OutputClearingMiddleware();
    }
}
