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

namespace CommonDocTest;

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\CommandHandler\BuildSiteHandler;
use League\CommonMark\ConverterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\MessageBus\MessageStatus;

#[CoversClass(BuildSiteHandler::class)]
#[CoversMethod(BuildSiteHandler::class, 'handle')]
final class BuildSiteHandlerTest extends TestCase
{
    #[Test]
    public function reportsAnEmptySuccessfulResult(): void
    {
        $command = new BuildSiteCommand(
            source: 'docs',
            output: 'build',
        );
        $handler = new BuildSiteHandler($this->createStub(ConverterInterface::class));
        $result  = $handler->handle($command);

        static::assertSame($command, $result->getCommand());
        static::assertSame(MessageStatus::Success, $result->getStatus());
        static::assertSame([], $result->getResult());
    }
}
