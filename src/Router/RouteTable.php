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

namespace CommonDoc\Router;

use InvalidArgumentException;
use LogicException;
use Mezzio\Router\RouteCollectorInterface;
use Override;
use Webware\Router\RouteProviderInterface;

use function sprintf;

/**
 * The routes one build discovered, named by local path.
 *
 * The route name is the local path, so the identifier a template uses and the
 * target a Markdown link contains are the same string. That is deliberate: two
 * identifier schemes, one for the router and one for links, would have to be
 * kept in sync, and a desynchronised pair of identifiers is exactly the defect
 * this design exists to remove.
 *
 * A table is filled during the scan pass and read during the render pass, so the
 * container carries this instance rather than a frozen route set built before
 * anything has been discovered. A router seeded from the previous build would be
 * correct one run later, never during the run that needs it.
 *
 * The options a route carries are the same options navigation reads, so a
 * document that is navigable and a document that renders a title get that
 * information from one source.
 *
 * @api
 */
final readonly class RouteTable implements RouteProviderInterface
{
    /**
     * Record that one local path is published at one address.
     *
     * @param non-empty-string $localPath
     * @param non-empty-string $publishedPath
     *
     * @throws InvalidArgumentException When either path cannot name a route, or the
     *                                  published path is already claimed
     * @throws LogicException           Until registration is implemented
     */
    public function add(string $localPath, string $publishedPath): void
    {
        throw new LogicException(sprintf(
            '%s::add() is not implemented for "%s" at "%s".',
            self::class,
            $localPath,
            $publishedPath,
        ));
    }

    /**
     * @throws LogicException Until registration is implemented
     */
    #[Override]
    public function registerRoutes(RouteCollectorInterface $routeCollector): void
    {
        throw new LogicException(sprintf('%s::registerRoutes() is not implemented.', self::class));
    }
}
