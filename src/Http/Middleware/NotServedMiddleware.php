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

namespace CommonDoc\Http\Middleware;

use CommonDoc\Exception\LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function sprintf;

/**
 * The middleware a build route carries, where nothing is ever served.
 *
 * A route needs middleware to exist, and a build never dispatches one, so the
 * only honest behaviour here is to say so. Throwing is deliberate: if this is
 * ever reached, a route registered by a build has been mounted somewhere that
 * serves requests, and a silent success would hide that.
 *
 * This lives under `Http\Middleware` because PSR-15 is permitted only there,
 * which is the boundary rule doing its job: a documentation builder that starts
 * accepting requests has stopped being a documentation builder.
 *
 * @internal
 */
final readonly class NotServedMiddleware implements MiddlewareInterface
{
    /**
     * @throws LogicException always, because a build route is never served
     */
    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        throw new LogicException(sprintf(
            'The route "%s" was registered by a documentation build and cannot serve a request.',
            $request->getUri()->getPath(),
        ));
    }
}
