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

namespace CommonDoc\Middleware;

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\Exception\ExceptionInterface;
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Exception\LogicException;
use CommonDoc\Record\Progress;
use CommonDoc\Record\Reason;
use Override;
use Psl\Exception\ExceptionInterface as PslExceptionInterface;
use Psl\File;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;
use Webware\FileWriter\FileWriter;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MiddlewareInterface;
use Webware\MessageBus\PipelineHandlerInterface;
use Webware\MessageBus\ResultInterface;

use function rtrim;
use function sprintf;

/**
 * Copies the assets, before the documents are rendered.
 *
 * Copying needs no environment, no parse and no tree, so it does not belong in a
 * loop that exists to drive CommonMark. It is a pass over the same scan, and
 * nothing about a stylesheet has anything to do with a document being converted.
 *
 * It is a phase rather than a step inside the handler for the reason D21 gives:
 * when one object holds every concern, adding a concern has nowhere to go except
 * into it. A pass that has its own position in the pipeline can be reasoned about,
 * tested and reordered without touching the coordinator.
 *
 * Ordering is deliberate. It runs above the handler, so every asset is in place
 * before the first document is rendered, and a failure is recorded before the
 * documents are attempted rather than after. It runs below the scan, because the
 * scan is what says which files are assets.
 *
 * A copy that fails is recorded and the pass continues. Stopping at the first
 * unreadable asset would name one file, and the operator wants the list.
 *
 * @api
 */
final readonly class AssetCopyMiddleware implements MiddlewareInterface
{
    /**
     * Where one asset is written: its own published path, under the output root.
     *
     * An asset is not translated. It sits where it is published, so its output file
     * is its own site path and there is nothing to derive.
     *
     * @return non-empty-string
     */
    private static function target(string $target, string $path): string
    {
        return sprintf('%s/%s', rtrim($target, characters: '/\\'), $path);
    }

    /**
     * @throws InvalidArgumentException When a scanned asset has no source
     * @throws LogicException When the scan phase has not run
     */
    #[Override]
    public function process(MessageInterface $message, PipelineHandlerInterface $next): ResultInterface
    {
        if (! $message instanceof BuildSiteCommand) {
            return $next->handle($message);
        }

        return $next->handle($message->withProgress($this->copy($message)));
    }

    /**
     * Copy every asset the scan found, and report what happened to each.
     *
     * The two refusals are the message's own: a request with no scan, and a path
     * the scan did not find. Neither is something a deployment can cause, so both
     * propagate rather than being recorded against a file. A recorded reason would
     * blame an asset for a defect in this package, which is the failure the reason
     * union exists to avoid.
     *
     * @throws InvalidArgumentException When a scanned asset has no source
     * @throws LogicException When the scan phase has not run
     */
    private function copy(BuildSiteCommand $command): Progress
    {
        $scan   = $command->getScan();
        $target = $command->getOutput();

        $output = [];
        $failed = [];

        foreach ($scan->assets as $asset) {
            try {
                FileWriter::writeTree(
                    self::target(
                        target: $target,
                        path  : $asset,
                    ),
                    File\read($scan->sourceOf($asset)),
                );
                $output[] = $asset;
            } catch (ExceptionInterface|FileWriterException|PslExceptionInterface $failure) {
                $failed[$asset] = Reason::of($failure);
            }
        }

        return new Progress(
            output: $output,
            failed: $failed,
        );
    }
}
