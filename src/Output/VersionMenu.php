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
use CommonDoc\Input\Release;
use CommonDoc\Input\Root;
use InvalidArgumentException as NativeInvalidArgumentException;
use Mezzio\Helper\Exception\RuntimeException as HelperException;
use Mezzio\Router\Exception\RuntimeException as RouterException;

use function ltrim;
use function sprintf;
use function str_starts_with;
use function usort;

/**
 * Every version of every component a site holds, answered from its roots.
 *
 * Two questions are asked of it: which version is the latest of each component, so a
 * component's own address can lead there, and which versions a page's component has, so a
 * reader can move between them. Both are answered from the roots a build was given, so
 * nothing is configured twice.
 *
 * A root that is not a released version is ignored, which is the ordinary shape of a site
 * with one unversioned repository.
 *
 * @type Entry = array{root: Root, release: Release}
 *
 * @api
 */
final readonly class VersionMenu
{
    /** @var list<Entry> Newest first, so the first of a component is its latest */
    private array $versions;

    /**
     * @param list<Root> $roots Every root the site is composed from
     */
    public function __construct(array $roots)
    {
        $versions = [];

        foreach ($roots as $root) {
            if (null === $root->release) {
                continue;
            }

            $versions[] = ['root' => $root, 'release' => $root->release];
        }

        usort(
            $versions,
            /**
             * @param Entry $a
             * @param Entry $b
             */
            static fn(array $a, array $b): int => $b['release']->version->major <=> $a['release']->version->major,
        );

        $this->versions = $versions;
    }

    /**
     * The newest version of each component, keyed by the component's path segment.
     *
     * @return array<string, Root>
     */
    public function latest(): array
    {
        $latest = [];

        foreach ($this->versions as $version) {
            $latest[$version['release']->component->segment] ??= $version['root'];
        }

        return $latest;
    }

    /**
     * The versions of the component a document belongs to, newest first, or none when the
     * document is not part of a versioned component.
     *
     * @param non-empty-string $sitePath The document's site path
     *
     * @return list<array{label: string, url: string, current: bool}>
     *
     * @throws InvalidArgumentException When a version's index is not a document path
     * @throws HelperException When the pattern route cannot be generated from
     * @throws RouterException When the pattern route cannot be generated from
     * @throws NativeInvalidArgumentException When the helper refuses a malformed fragment. Nothing
     *         here passes a fragment, so this is declared rather than reachable
     */
    public function links(string $sitePath, DocumentRoute $route): array
    {
        $own = $this->componentOf($sitePath);

        if (null === $own) {
            return [];
        }

        $links  = [];
        $newest = true;

        foreach ($this->versions as $version) {
            if ($version['release']->component->segment !== $own) {
                continue;
            }

            $links[] = [
                'label'   => sprintf('v%d%s', $version['release']->version->major, $newest ? ' (latest)' : ''),
                'url'     => $route->url(ltrim($version['root']->mount, characters: '/') . 'index.md'),
                'current' => str_starts_with("/{$sitePath}", $version['root']->mount),
            ];

            $newest = false;
        }

        return $links;
    }

    /**
     * The segment of the component whose version mount holds a document, or null.
     */
    private function componentOf(string $sitePath): ?string
    {
        foreach ($this->versions as $version) {
            if (str_starts_with("/{$sitePath}", $version['root']->mount)) {
                return $version['release']->component->segment;
            }
        }

        return null;
    }
}
