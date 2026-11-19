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
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Scan;
use Override;
use Psl\Filesystem;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MiddlewareInterface;
use Webware\MessageBus\PipelineHandlerInterface;
use Webware\MessageBus\ResultInterface;

/**
 * Finds the sources, before anything tries to read one.
 *
 * Discovery is cross-document work. It has to finish for every root before the first
 * document is converted, because a document may link to one the build has not reached
 * yet, and CommonMark cannot answer it: its contract is a string in and a tree out,
 * with no notion of a file or a directory.
 *
 * So it is a phase. It runs at priority 50, after the pre-handle event and before the
 * handler, and it hands its result forward on the message. A handler that discovered
 * as well would be the only place anything happened, which is the arrangement this
 * pipeline exists to replace.
 *
 * The refusal is a throw rather than a failed result. A root that is not a readable
 * directory is not a build that produced nothing; it is a request that cannot be
 * honoured, and the exception carries the path so the operator can act on it. The
 * console layer renders it and exits `INVALID`.
 *
 * A message that is not a build request is passed straight through: this middleware is
 * registered on the bus rather than on one command, so it has to leave anything else
 * alone.
 *
 * @api
 */
final readonly class SourceLoadingMiddleware implements MiddlewareInterface
{
    /**
     * @throws InvalidArgumentException When a configured root is not a readable directory
     * @throws Filesystem\Exception\NotFoundException When a directory disappears mid-walk
     * @throws Filesystem\Exception\NotDirectoryException When an entry stops being a directory
     * @throws Filesystem\Exception\NotReadableException When a directory becomes unreadable
     */
    #[Override]
    public function process(MessageInterface $message, PipelineHandlerInterface $next): ResultInterface
    {
        if (! $message instanceof BuildSiteCommand) {
            return $next->handle($message);
        }

        return $next->handle($message->withScan(Scan::of($message->getRoots())));
    }
}
