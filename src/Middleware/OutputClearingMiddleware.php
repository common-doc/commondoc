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
use CommonDoc\Input\Root;
use CommonDoc\Output\OutputDirectory;
use InvalidArgumentException;
use Override;
use Psl\Filesystem;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MiddlewareInterface;
use Webware\MessageBus\PipelineHandlerInterface;
use Webware\MessageBus\ResultInterface;

use function array_map;

/**
 * Clears the output directory, before anything writes into it.
 *
 * A build that only adds files produces the union of every build ever run into that
 * directory, so a page that was deleted or renamed stays published and the next deploy
 * carries it forward. That is invisible until someone looks for a page that should be gone,
 * and an automated deploy has nobody looking.
 *
 * It is the first phase in the pipeline because everything after it writes: the assets, the
 * documents, the pages that belong to no document and the search index. Clearing inside the
 * handler would be too late for the assets and the redirects, which are written by middleware
 * around it.
 *
 * A phase rather than a step in the command for the same reason the other phases are: it has
 * its own position, it can be reasoned about and tested on its own, and a listener has one
 * place to observe it. The command resolves the output and the roots, so nothing here decides
 * where the site goes; it is handed a build and prepares the ground for it.
 *
 * Clearing is destructive, so the path is refused rather than warned about when it cannot be
 * a site's output. `OutputDirectory` owns that rule, and it refuses before anything is
 * removed. Only a build clears: any other message passes through untouched.
 *
 * @api
 */
final readonly class OutputClearingMiddleware implements MiddlewareInterface
{
    /**
     * @throws InvalidArgumentException When the output directory cannot be a site's output
     * @throws Filesystem\Exception\ExceptionInterface When the output directory cannot be cleared
     */
    #[Override]
    public function process(MessageInterface $message, PipelineHandlerInterface $next): ResultInterface
    {
        if (! $message instanceof BuildSiteCommand) {
            return $next->handle($message);
        }

        new OutputDirectory(
            path   : $message->getOutput(),
            sources: array_map(
                static fn(Root $root): string => $root->directory(),
                $message->getRoots(),
            ),
        )->clear();

        return $next->handle($message);
    }
}
