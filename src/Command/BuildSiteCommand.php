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

namespace CommonDoc\Command;

use Override;
use Webware\MessageBus\Command\NamedCommandInterface;

/**
 * Bus command asking for the site to be built.
 *
 * Implements NamedCommandInterface, which extends CommandInterface: the guard
 * requires the named form of every class in a `Command\` namespace.
 *
 * @api
 */
final readonly class BuildSiteCommand implements NamedCommandInterface
{
    public function __construct(
        private string $source,
        private string $output,
    ) {}

    #[Override]
    public function getCommandName(): string
    {
        return self::class;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
