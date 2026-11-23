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
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use CommonDoc\Input\Scan;
use CommonDoc\Middleware\OutputClearingMiddleware;
use CommonDocTest\Support\AbstractTemporaryTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MessageStatus;
use Webware\MessageBus\PipelineHandlerInterface;

use function is_file;

/**
 * The phase that empties the output directory before anything writes into it.
 */
#[CoversClass(OutputClearingMiddleware::class)]
#[CoversMethod(OutputClearingMiddleware::class, 'process')]
final class OutputClearingMiddlewareTest extends AbstractTemporaryTree
{
    #[Test]
    public function leavesAnyOtherMessageAlone(): void
    {
        $this->makeTree();
        $this->writeFile('build/kept.html');

        $message = new class implements MessageInterface {};

        $next = $this->createStub(PipelineHandlerInterface::class);
        $next->method('handle')->willReturn(new CommandResult(
            command: $this->command(),
            status : MessageStatus::Success,
            result : null,
        ));

        new OutputClearingMiddleware()->process($message, $next);

        static::assertTrue(is_file("{$this->tree}/build/kept.html"));
    }

    #[Test]
    public function passesTheMessageOnAndReturnsItsResult(): void
    {
        $this->makeTree();

        $command = $this->command();
        $result  = new CommandResult(
            command: $command,
            status : MessageStatus::Success,
            result : null,
        );

        $next = $this->createMock(PipelineHandlerInterface::class);
        $next->expects($this->once())->method('handle')->with($command)->willReturn($result);

        static::assertSame($result, new OutputClearingMiddleware()->process($command, $next));
    }

    #[Test]
    public function refusesAnOutputThatHoldsASource(): void
    {
        $this->makeTree();

        $this->expectException(InvalidArgumentException::class);

        new OutputClearingMiddleware()->process(
            $this->command(
                output: "{$this->tree}",
                root  : "{$this->tree}/docs",
            ),
            $this->createStub(PipelineHandlerInterface::class),
        );
    }

    #[Test]
    public function removesWhatAPreviousBuildLeft(): void
    {
        $this->makeTree();
        $this->writeFile('build/stale/index.html');

        $this->process($this->command());

        static::assertFalse(is_file("{$this->tree}/build/stale/index.html"));
    }

    private function command(?string $output = null, ?string $root = null): BuildSiteCommand
    {
        return new BuildSiteCommand(
            roots : [new Root(path: $root ?? "{$this->tree}/docs")],
            output: $output ?? "{$this->tree}/build",
            scan  : new Scan(
                sources  : [],
                documents: [],
                assets   : [],
            ),
        );
    }

    private function process(BuildSiteCommand $command): void
    {
        $next = $this->createStub(PipelineHandlerInterface::class);
        $next->method('handle')->willReturn(new CommandResult(
            command: $command,
            status : MessageStatus::Success,
            result : null,
        ));

        new OutputClearingMiddleware()->process($command, $next);
    }
}
