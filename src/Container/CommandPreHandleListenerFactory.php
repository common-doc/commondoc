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

use CommonDoc\Listener\CommandPreHandleListener;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Construction seam for the command pre-handle listener.
 *
 * The listener takes no collaborators yet. This factory is the single place
 * they get injected once the listener gains behaviour.
 *
 * @internal
 */
final readonly class CommandPreHandleListenerFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): CommandPreHandleListener {
        return new CommandPreHandleListener();
    }
}
