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
 * Wraps the handler's rendered pages in the site layout.
 *
 * Registered at priority 0, so it runs after the handler and before the
 * post-handle event: a diagnostics listener sees the wrapped result rather than
 * the raw one. The body is a pass-through for now.
 *
 * @api
 */
final readonly class LayoutWrappingMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(MessageInterface $message, PipelineHandlerInterface $next): ResultInterface
    {
        return $next->handle($message);
    }
}
