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
use CommonDoc\Exception\LogicException;
use CommonDoc\Input\Root;
use CommonDoc\Input\Scan;
use CommonDoc\Middleware\AssetCopyMiddleware;
use CommonDoc\Record\Outcome;
use CommonDocTest\Support\AbstractTemporaryTree;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Webware\MessageBus\Command\CommandResult;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MessageStatus;
use Webware\MessageBus\PipelineHandlerInterface;

use function array_keys;
use function file_get_contents;
use function is_file;
use function mkdir;

/**
 * The asset pass is a phase, and this is where it runs.
 *
 * Copying has to finish before the first document is rendered, and what it did has
 * to reach the record, so it hands an `Outcome` forward on the message rather than
 * reporting for itself.
 */
#[CoversClass(AssetCopyMiddleware::class)]
#[CoversMethod(AssetCopyMiddleware::class, 'process')]
final class AssetCopyMiddlewareTest extends AbstractTemporaryTree
{
    /** @var string */
    private $source = '';
    /** @var string */
    private $output = '';

    #[Test]
    public function carriesOnAfterAnAssetFails(): void
    {
        $this->writeFile('source/site.css', contents: 'body{}');
        $this->writeFile('source/images/d.png', contents: 'PNG');

        mkdir("{$this->output}/site.css", recursive: true);

        $forwarded = $this->forwarded($this->command());

        static::assertInstanceOf(BuildSiteCommand::class, $forwarded);
        // Stopping at the first unreadable asset would name one file, and the
        // operator wants the list.
        static::assertSame(['images/d.png'], $forwarded->getOutcome()->output);
        static::assertTrue(is_file("{$this->output}/images/d.png"));
    }

    #[Test]
    public function copiesAnAssetWhereItSits(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/images/d.png', contents: 'PNG');

        $forwarded = $this->forwarded($this->command());

        // An asset is not translated, so the output file is its own site path.
        static::assertSame('PNG', (string) file_get_contents("{$this->output}/images/d.png"));
        static::assertInstanceOf(BuildSiteCommand::class, $forwarded);
        static::assertSame(['images/d.png'], $forwarded->getOutcome()->output);
        static::assertSame([], $forwarded->getOutcome()->failed);
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
        static::assertSame($result, new AssetCopyMiddleware()->process($message, $next));
    }

    #[Test]
    public function namesAnAssetThatCouldNotBeCopied(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");
        $this->writeFile('source/site.css', contents: 'body{}');

        // A directory where the file belongs, so the copy cannot land. The point
        // is not the reason but the fact that the asset is named at all: a build
        // that stopped without a path would leave an operator with a broken site
        // and nothing to fix.
        mkdir("{$this->output}/site.css", recursive: true);

        $forwarded = $this->forwarded($this->command());

        static::assertInstanceOf(BuildSiteCommand::class, $forwarded);

        $outcome = $forwarded->getOutcome();

        static::assertSame([], $outcome->output);
        static::assertSame(['site.css'], array_keys($outcome->failed));
        static::assertNotSame('', $outcome->failed['site.css']);
    }

    #[Test]
    public function refusesWhenTheScanPhaseHasNotRun(): void
    {
        $this->writeFile('source/index.md', contents: "# Home\n");

        $roots = [new Root(path: $this->source)];

        $this->expectException(LogicException::class);

        $this->forwarded(new BuildSiteCommand(
            roots : $roots,
            output: $this->output,
        ));
    }

    protected function setUp(): void
    {
        $this->makeTree();

        $this->source = "{$this->tree}/source";
        $this->output = "{$this->tree}/build";

        mkdir($this->source, recursive: true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree();
    }

    private function command(): BuildSiteCommand
    {
        $roots = [new Root(path: $this->source)];

        return new BuildSiteCommand(
            roots : $roots,
            output: $this->output,
            scan  : Scan::of($roots),
        );
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
                        command: $forwarded,
                        status : MessageStatus::Success,
                        result : null,
                    );
                },
            );

        new AssetCopyMiddleware()->process($message, $next);

        return $seen;
    }
}
