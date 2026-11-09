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

namespace CommonDoc\Listener;

use Override;
use Webware\Event\EventInterface;
use Webware\Event\ListenerInterface;

/**
 * Reacts to a command after the bus handler returns.
 *
 * The event exposes both the command through getCommand() and the handler's
 * result through getResult(). There is no behaviour yet: registration is the
 * whole point of this pass.
 *
 * @api
 */
final readonly class CommandPostHandleListener implements ListenerInterface
{
    #[Override]
    public function __invoke(EventInterface $event): void {}
}
