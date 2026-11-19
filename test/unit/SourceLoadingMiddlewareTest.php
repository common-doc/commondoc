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
use CommonDoc\Middleware\SourceLoadingMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MessageStatus;
use Webware\MessageBus\PipelineHandlerInterface;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * The scan is a phase, and this is where it runs.
 *
 * It has to finish for every root before the first document is converted, because a
 * document may link to one the build has not reached yet. What it found travels
 * forward on the message, so the handler reads discovery instead of performing it.
 */
#[CoversClass(SourceLoadingMiddleware::class)]
#[CoversMethod(SourceLoadingMiddleware::class, 'process')]
final class SourceLoadingMiddlewareTest extends TestCase
{
    #[Test]
    public function handsTheScanForwardOnTheMessage(): void
    {
        $tree = $this->temporaryTree();

        try {
            file_put_contents(
                filename: "{$tree}/index.md",
                data    : "# Home\n",
            );
            file_put_contents(
                filename: "{$tree}/site.css",
                data    : 'body{}',
            );

            $message = new BuildSiteCommand(
                roots : [new Root(path: $tree)],
                output: 'build',
            );

            $forwarded = $this->forwarded($message);

            static::assertInstanceOf(BuildSiteCommand::class, $forwarded);
            static::assertSame(['index.md'], $forwarded->getScan()->documents);
            static::assertSame(['site.css'], $forwarded->getScan()->assets);
            static::assertSame("{$tree}/index.md", $forwarded->getScan()->sourceOf('index.md'));
        } finally {
            $this->removeDirectory($tree);
        }
    }

    #[Test]
    public function leavesAnyOtherMessageAlone(): void
    {
        $message = new class implements MessageInterface {};
        $result  = new CommandResult(
            command: new BuildSiteCommand(
                roots : [new Root(path: 'docs')],
                output: 'build',
            ),
            status : MessageStatus::Success,
            result : null,
        );

        $next = $this->createMock(PipelineHandlerInterface::class);
        $next->expects($this->once())->method('handle')->with($message)->willReturn($result);

        // The middleware is on the bus rather than on one command, so it cannot
        // assume every message is a build request.
        static::assertSame($result, new SourceLoadingMiddleware()->process($message, $next));
    }

    #[Test]
    public function mountsEachRootAsItReadsIt(): void
    {
        $tree = $this->temporaryTree();

        try {
            mkdir("{$tree}/one", recursive: true);
            mkdir("{$tree}/two", recursive: true);

            file_put_contents(
                filename: "{$tree}/one/index.md",
                data    : "# One\n",
            );
            file_put_contents(
                filename: "{$tree}/two/index.md",
                data    : "# Two\n",
            );

            $message = new BuildSiteCommand(
                roots : [
                    new Root(
                        path : "{$tree}/one",
                        mount: '/one/',
                    ),
                    new Root(
                        path : "{$tree}/two",
                        mount: '/two/',
                    ),
                ],
                output: 'build',
            );

            $forwarded = $this->forwarded($message);

            static::assertInstanceOf(BuildSiteCommand::class, $forwarded);
            // Named by address rather than by repository: from here on nothing knows
            // the site was composed.
            static::assertSame(['one/index.md', 'two/index.md'], $forwarded->getScan()->documents);
        } finally {
            $this->removeDirectory($tree);
        }
    }

    #[Test]
    public function refusesARootThatIsNotADirectory(): void
    {
        $message = new BuildSiteCommand(
            roots : [new Root(path: 'does-not-exist')],
            output: 'build',
        );

        $this->expectException(InvalidArgumentException::class);

        $this->forwarded($message);
    }

    private function forwarded(BuildSiteCommand $message): mixed
    {
        $seen = null;

        $next = $this->createStub(PipelineHandlerInterface::class);
        $next
            ->method('handle')
            ->willReturnCallback(
                static function (MessageInterface $forwarded) use (&$seen): CommandResult {
                    $seen = $forwarded;

                    return new CommandResult(
                        command: new BuildSiteCommand(
                            roots : [new Root(path: 'docs')],
                            output: 'build',
                        ),
                        status : MessageStatus::Success,
                        result : null,
                    );
                },
            );

        new SourceLoadingMiddleware()->process($message, $next);

        return $seen;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());

                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($directory);
    }

    private function temporaryTree(): string
    {
        $tree = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'commondoc-scan-' . bin2hex(random_bytes(8));

        mkdir($tree, recursive: true);

        return $tree;
    }
}
