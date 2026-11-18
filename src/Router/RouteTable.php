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

use CommonDoc\Http\Middleware\NotServedMiddleware;
use InvalidArgumentException;
use Mezzio\Router\RouteCollectorInterface;
use Override;
use Webware\Router\RouteProviderInterface;

use function array_key_exists;
use function count;
use function sprintf;
use function str_starts_with;

/**
 * The routes one build discovered, named by local path.
 *
 * Two names for one document, and the split is the whole point:
 *
 * - The route **name** is the local path, `guide/setup.md`. That is the string a
 *   markdown link already contains, so the identifier a template uses and the
 *   target a link carries are the same token rather than two schemes kept in
 *   sync by hand.
 * - The route **path** is the published path, `/guide/setup/`. Navigation renders
 *   `href` from `Route::getPath()`, so putting the published path there is what
 *   makes `$this->navigation('docs')->menu()` emit correct links in a build with
 *   no request and no URL helper.
 *
 * The options a route carries are the ones navigation reads: `label`,
 * `navigation`, `parent` and `order` are handed to `NavigationItem` unchanged.
 * Nothing here interprets them, and nothing here requires a particular set: a
 * caller that puts more in the array gets the same array back from
 * `options()`.
 *
 * A table is filled during the scan pass and read during the render pass, so the
 * container carries this instance rather than a frozen route set built before
 * anything has been discovered. A router seeded from the previous build would be
 * correct one run later and never during the run that needs it.
 *
 * That is also why this is not a readonly value object: it accumulates. The
 * arrays it holds are only ever added to, and every read is a lookup, so the
 * mutable surface is one method wide.
 *
 * @api
 */
final class RouteTable implements RouteProviderInterface
{
    /**
     * @var array<non-empty-string, non-empty-string> Published path by local path
     */
    private array $published;

    /**
     * @var array<non-empty-string, array<array-key, mixed>> Route options by local path
     */
    private array $options;

    /**
     * @throws InvalidArgumentException When the table is given inconsistent state
     */
    public function __construct()
    {
        $this->published = [];
        $this->options   = [];
    }

    /**
     * Record that one local path is published at one address.
     *
     * Refuses a published path that is already claimed by a different document,
     * because two documents at one address means one of them silently overwrites
     * the other. That is checked here rather than at write time, where the damage
     * is already done.
     *
     * Re-adding the same local path with the same published path updates its
     * options, which is what lets a caller enrich a route it registered during
     * the scan.
     *
     * @param string                      $localPath     The route name, and the key every list shares
     * @param string                      $publishedPath The route path, as navigation renders it
     * @param array<array-key, mixed>     $options       Navigation options, stored unchanged
     *
     * @throws InvalidArgumentException When either path is empty or the published path is taken
     */
    public function add(string $localPath, string $publishedPath, array $options = []): void
    {
        if ('' === $localPath || '' === $publishedPath) {
            throw new InvalidArgumentException(
                'A route requires a local path and a published path.',
            );
        }

        if (! str_starts_with($publishedPath, '/')) {
            throw new InvalidArgumentException(sprintf(
                'The published path "%s" for "%s" must start with a slash.',
                $publishedPath,
                $localPath,
            ));
        }

        $existing = $this->published[$localPath] ?? null;

        if (null !== $existing && $existing !== $publishedPath) {
            throw new InvalidArgumentException(sprintf(
                'The document "%s" is already published at "%s" and cannot move to "%s".',
                $localPath,
                $existing,
                $publishedPath,
            ));
        }

        foreach ($this->published as $otherLocal => $otherPublished) {
            if ($otherLocal !== $localPath && $otherPublished === $publishedPath) {
                throw new InvalidArgumentException(sprintf(
                    'The published path "%s" is claimed by both "%s" and "%s".',
                    $publishedPath,
                    $otherLocal,
                    $localPath,
                ));
            }
        }

        $this->published[$localPath] = $publishedPath;
        $this->options[$localPath]   = $options;
    }

    /**
     * How many routes the build discovered.
     *
     * @return non-negative-int
     */
    public function count(): int
    {
        return count($this->published);
    }

    /**
     * Whether a document with this local path was registered.
     */
    public function has(string $localPath): bool
    {
        return array_key_exists($localPath, $this->published);
    }

    /**
     * The options a local path carries, or an empty array when it is not registered.
     *
     * @return array<array-key, mixed>
     */
    public function options(string $localPath): array
    {
        return $this->options[$localPath] ?? [];
    }

    /**
     * The published path a local path maps to, or null when it is not registered.
     */
    public function published(string $localPath): ?string
    {
        return $this->published[$localPath] ?? null;
    }

    /**
     * Register every route on the collector the router reads.
     *
     * @throws InvalidArgumentException When the collector refuses a path
     */
    #[Override]
    public function registerRoutes(RouteCollectorInterface $routeCollector): void
    {
        foreach ($this->published as $localPath => $publishedPath) {
            $route = $routeCollector->get(
                path      : $publishedPath,
                middleware: new NotServedMiddleware(),
                name      : $localPath,
            );

            $route->setOptions($this->options[$localPath] ?? []);
        }
    }
}
