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

use Override;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MiddlewareInterface;
use Webware\MessageBus\PipelineHandlerInterface;
use Webware\MessageBus\ResultInterface;

/**
 * Loads the Markdown sources before the bus reaches the handler.
 *
 * Registered at priority 50, so it runs after the pre-handle event and before
 * the handler. The body is a pass-through for now: this pass wires the pipeline
 * position, not the loading itself.
 *
 * @api
 */
final readonly class SourceLoadingMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(MessageInterface $message, PipelineHandlerInterface $next): ResultInterface
    {
        return $next->handle($message);
    }
}
