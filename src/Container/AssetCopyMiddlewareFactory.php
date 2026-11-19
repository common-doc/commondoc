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

use CommonDoc\Middleware\AssetCopyMiddleware;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Construction seam for the asset pass.
 *
 * The middleware takes no collaborators: everything it needs is on the message it
 * is handed, and the writer it writes through is static. This factory exists so
 * the pipeline can resolve it by name and so a collaborator has one place to be
 * injected when it gains one.
 *
 * @internal
 */
final readonly class AssetCopyMiddlewareFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): AssetCopyMiddleware {
        return new AssetCopyMiddleware();
    }
}
