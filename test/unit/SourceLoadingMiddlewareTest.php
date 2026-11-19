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
use CommonDoc\Input\Root;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\MessageStatus;
use Webware\MessageBus\PipelineHandlerInterface;

#[CoversClass(SourceLoadingMiddleware::class)]
#[CoversMethod(SourceLoadingMiddleware::class, 'process')]
final class SourceLoadingMiddlewareTest extends TestCase
{
    #[Test]
    public function forwardsTheMessageAndReturnsTheHandlerResult(): void
    {
        $message = new BuildSiteCommand(
            roots : [new Root(path: 'docs')],
            output: 'build',
        );
        $result = new CommandResult(
            command: $message,
            status : MessageStatus::Success,
            result : [],
        );

        $next = $this->createMock(PipelineHandlerInterface::class);
        $next
            ->expects($this->once())
            ->method('handle')
            ->with($message)
            ->willReturn($result);

        static::assertSame($result, new SourceLoadingMiddleware()->process($message, $next));
    }
}
