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

use CommonDoc\Middleware\SourceLoadingMiddleware;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Construction seam for the source loading middleware.
 *
 * This factory is load bearing: the pipeline factory skips any spec the
 * container cannot resolve, so without it the middleware would vanish from the
 * pipeline without an error.
 *
 * @internal
 */
final readonly class SourceLoadingMiddlewareFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): SourceLoadingMiddleware {
        return new SourceLoadingMiddleware();
    }
}
