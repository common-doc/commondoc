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

namespace CommonDoc\Container;

use Closure;
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Source;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\SiteConfig;
use CommonDoc\View\Page;
use InvalidArgumentException as NativeInvalidArgumentException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Mezzio\Helper\Exception\RuntimeException as HelperException;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Builds the function a build calls to get the page for one document.
 *
 * A page belongs to one document, so it cannot be a shared service, and the handler
 * must not ask a container for one. It is handed a function instead, registered under
 * this class's own name: what the container serves for that name is the callable this
 * returns, not an instance of this class.
 *
 * The collaborators are resolved here once and captured by value, so the callable
 * never sees the container. Everything shared by every page in a deployment (the
 * project name, the slug, the version label) comes from `SiteConfig`. The URL comes
 * from the routing table through the URL helper rather than from a translation computed
 * here, so a page cannot carry an address the router disagrees with.
 *
 * What differs per document is typed arguments rather than an options array, so a
 * caller that omits one fails at the call and not at a key read.
 *
 * @internal
 */
final readonly class PageFactoryFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     *
     * @return Closure(non-empty-string $localPath, string $label, ?Source $source, ?string $repositoryPath): Page
     *
     * @throws ContainerExceptionInterface When a collaborator cannot be fetched
     * @throws NotFoundExceptionInterface When a collaborator is not registered
     * @throws Type\Exception\ExceptionInterface When a collaborator is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Closure {
        $site = Type\instance_of(SiteConfig::class)
            ->coerce($container->get(SiteConfig::class));
        $route = Type\instance_of(DocumentRoute::class)
            ->coerce($container->get(DocumentRoute::class));

        return /**
         * @param non-empty-string $localPath      The document's site path, as the scan produced it
         * @param string           $label          The label the page carries
         * @param ?Source          $source         The repository the document was read from, if it is a checkout
         * @param ?string          $repositoryPath The document's path from the repository root, if it is a checkout
         *
         * @throws InvalidArgumentException When the local path does not name a relative markdown path
         * @throws HelperException When the route for this document cannot be generated from, which
         *         means the build did not register it
         * @throws NativeInvalidArgumentException When the helper refuses a malformed fragment. Nothing
         *         here passes a fragment, so this is declared rather than reachable
         */ static function (
            string $localPath,
            string $label,
            ?Source $source = null,
            ?string $repositoryPath = null,
        ) use ($site, $route): Page {
            // The page's route name is its URL: the registrar registers each document's
            // route at the URL the pattern yields and names it by that path.
            $url = $route->url($localPath);

            return new Page(
                label         : $label,
                projectName   : $site->projectName,
                projectSlug   : $site->projectSlug,
                sourcePath    : $localPath,
                url           : $url,
                versionLabel  : $site->versionLabel,
                routeName     : $url,
                source        : $source,
                repositoryPath: $repositoryPath,
            );
        };
    }
}
