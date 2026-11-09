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

namespace CommonDoc\CommandHandler;

use CommonDoc\Command\BuildSiteCommand;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\Command\CommandResultInterface;
use Webware\MessageBus\CommandHandlerInterface;
use Webware\MessageBus\MessageStatus;

/**
 * Handles the build site command.
 *
 * @api
 */
final readonly class BuildSiteHandler implements CommandHandlerInterface
{
    public function handle(BuildSiteCommand $command): CommandResultInterface
    {
        return new CommandResult(
            command: $command,
            status : MessageStatus::Success,
            result : [],
        );
    }
}
