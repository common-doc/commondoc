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

namespace CommonDoc\Output;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Input\Markdown;
use CommonDoc\Input\Root;
use InvalidArgumentException as NativeInvalidArgumentException;
use Mezzio\Helper\Exception\RuntimeException as HelperException;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\RouteCollectorInterface;

use function array_pop;
use function explode;
use function strlen;
use function substr;

/**
 * Provides the routes a build generates URLs from and the menu reads.
 *
 * A build dispatches nothing. First the one pattern route every URL is generated from,
 * then one route per document at the URL that pattern yields, so the navigation helper
 * has a tree to read whose every path was composed by the router. That is why providing
 * the routes is its own collaborator rather than a step buried in the coordinator: it is
 * the one place a document becomes a route, and it is where a route tree grows.
 *
 * The name follows the ecosystem's, where a type that contributes routes to the routing
 * table is a provider.
 *
 * @api
 */
final readonly class RouteProvider
{
    private const string SEPARATOR = '/';

    /**
     * The navigation identifier of the list of components, which every page shows.
     *
     * A page's own menu is named by `NavigationScope`. This one is the same on every page,
     * so a reader can move from one component to another without going back to the home page.
     */
    public const string COMPONENTS = 'commondoc.components';

    public function __construct(
        private DocumentRoute $route,
        private RouteCollectorInterface $collector,
        private NotServedMiddleware $middleware,
    ) {}

    /**
     * A page label for a document, derived mechanically from its own path.
     *
     * The file name without its document suffix is used, with no special case
     * for `index`, because a better label needs a source this build does not have
     * yet: the document's first heading, or a front-matter field. Deriving
     * something deterministic is better than inventing navigation vocabulary
     * here, and the label is cosmetic until that source exists.
     */
    public static function labelFor(string $document): string
    {
        $segments = explode(self::SEPARATOR, $document);

        return substr(
            string: array_pop($segments),
            offset: 0,
            length: -strlen(Markdown::EXTENSION),
        );
    }

    /**
     * Register the pattern route, then one route per document at the URL it yields.
     *
     * Registration happens before the first render, so a link to a document the
     * loop has not reached yet still has a route, and so a URL can be generated for it
     * rather than computed by the caller. The collector refuses a duplicate name or
     * path, which is how two local paths that publish at one path are caught: nothing
     * here re-implements that check.
     *
     * The pattern route carries no navigation option, so the menu never lists it. Each
     * document route carries the options the navigation helper reads. They are set here
     * because registration is the only moment the route exists and the document is
     * known at once: a menu built later has nothing but the routing table, so an option
     * not written now cannot be recovered from a document later.
     *
     * @param list<non-empty-string> $documents
     * @param list<Root>             $roots     The roots the documents came from, which name each
     *                                          document's menu and the components listed beside it
     *
     * @throws DuplicateRouteException When two documents publish at one path
     * @throws InvalidArgumentException When a local path does not name a document
     * @throws HelperException When the pattern route cannot be generated from
     * @throws NativeInvalidArgumentException When the helper refuses a malformed fragment. Nothing
     *         here passes a fragment, so this is declared rather than reachable
     */
    public function register(array $documents, array $roots = []): void
    {
        $this->collector->route(
            path      : $this->route->pattern(),
            middleware: $this->middleware,
            name      : DocumentRoute::NAME,
        );

        $scope = new NavigationScope($roots);
        $order = 0;

        foreach ($documents as $document) {
            $options = ['order' => $order];

            // A directory's index is that directory's landing page, addressed by the
            // directory itself. Listing it beside the directory's pages would show one place
            // twice, so its route is registered for links and kept out of the menu.
            if (! $this->route->isIndex($document)) {
                $options['navigation'] = $scope->of($document);
                $options['label']      = self::labelFor($document);
            }

            // `route()` rather than `get()`: these routes exist to be generated from and
            // read by the navigation helper, and are never dispatched, so the method they
            // accept is meaningless. It matters because the router derives the name from
            // the path, and `HTTP_METHOD_ANY` derives it as the path itself, while `get()`
            // would derive `<path>^GET`. The name is the URL, which is what `Page` carries.
            $this->collector
                ->route(
                    path      : $this->route->url($document),
                    middleware: $this->middleware,
                )
                ->setOptions($options);

            $order++;
        }

        $this->registerComponents($roots);
    }

    /**
     * One route per released component, at the component's own address.
     *
     * The address is the page that redirects to the newest version, so it has no document
     * route of its own to collide with. A component that is not a release is not listed
     * here: its index is already a document route, and a second route at that path would be
     * refused as a duplicate.
     *
     * @param list<Root> $roots
     *
     * @throws DuplicateRouteException When a component's address is already a route
     * @throws InvalidArgumentException When a component's index is not a document path
     * @throws HelperException When the pattern route cannot be generated from
     * @throws NativeInvalidArgumentException When the helper refuses a malformed fragment
     */
    private function registerComponents(array $roots): void
    {
        $order = 0;

        foreach (new ComponentIndex($roots)->entries() as $entry) {
            if (null === $entry['segment']) {
                continue;
            }

            $this->collector
                ->route(
                    path      : $this->route->url($entry['index']),
                    middleware: $this->middleware,
                )
                ->setOptions([
                    'navigation' => self::COMPONENTS,
                    'label'      => $entry['title'],
                    'order'      => $order,
                ]);

            $order++;
        }
    }
}
