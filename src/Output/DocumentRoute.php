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
use InvalidArgumentException as NativeInvalidArgumentException;
use Mezzio\Helper\Exception\RuntimeException as HelperException;
use Mezzio\Helper\UrlHelper;

use function sprintf;

/**
 * The route every document's URL is generated from.
 *
 * A document is not a route of its own here. There is one route, a pattern with a
 * `{path}` parameter beneath the base path, and a document's URL is that pattern with the
 * value `DocumentRoot::pathOf()` answers substituted in. So the router composes every URL,
 * and nothing else prefixes a base path or appends a slash.
 *
 * The pattern route has to be registered before a URL is asked for. `RouteRegistrar` does
 * that at the start of a build, and a document asked for before it has no route to
 * generate from, which is a defect in the order of the build and is not answered with an
 * address composed here.
 *
 * @api
 */
final readonly class DocumentRoute
{
    /** The name the pattern route is registered under, and the only name a URL is generated from. */
    public const string NAME = 'commondoc.document';

    private const string PARAMETER = 'path';

    public function __construct(
        private DocumentRoot $root,
        private UrlHelper $url,
    ) {}

    /**
     * The file a local path is written to, relative to the output directory.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When the local path does not name a document
     */
    public function file(string $localPath): string
    {
        return $this->root->file($localPath);
    }

    /**
     * Whether a global path is the index of its own directory.
     *
     * A directory's index is that directory's landing page: it is addressed by the directory
     * itself, so listing it again beside the directory's other pages would show the same
     * place twice, once as the group and once as a member of it.
     *
     * @throws InvalidArgumentException When the local path does not name a document
     */
    public function isIndex(string $localPath): bool
    {
        return $this->root->isIndex($localPath);
    }

    /**
     * The pattern the route is registered with, such as `/repo/{path}`.
     *
     * @return non-empty-string
     */
    public function pattern(): string
    {
        return sprintf('%s{%s}', $this->root->basePath, self::PARAMETER);
    }

    /**
     * The URL a document is served at, such as `/repo/guide/setup/`.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When the local path does not name a document
     * @throws HelperException When the pattern route has not been registered
     * @throws NativeInvalidArgumentException When the helper refuses a malformed fragment. Nothing
     *         here passes a fragment, so this is declared rather than reachable
     */
    public function url(string $localPath): string
    {
        /** @var non-empty-string */
        return $this->url->generate(
            routeName  : self::NAME,
            routeParams: [self::PARAMETER => $this->root->pathOf($localPath)],
        );
    }
}
