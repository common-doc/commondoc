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

namespace CommonDoc;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use CommonDoc\Output\DocumentRoot;

use function sprintf;

/**
 * What a deployment is, read once from configuration.
 *
 * Everything here is constant for one site and cannot be discovered during a run:
 * the path space it publishes into, the source roots it is composed from, where
 * the result is written, and the identity every page shares. All of it is needed by
 * more than one collaborator, so reading it in each place that needs it would be one
 * rule with several copies.
 *
 * It is constructed at boot and never varies within a run. What a command line may
 * override is applied when the build request is made, so this object is what was
 * configured and the request is what this run does. Keeping those apart is what stops
 * a boot-time object from carrying a value that can still change.
 *
 * The base path is deliberately not overridable. It describes how the site is served
 * rather than how it is built: it is known before a run, and every path the build
 * emits is built from it, so a wrong value produces a site that looks right on disk
 * and resolves nothing once served.
 *
 * @api
 */
final readonly class SiteConfig
{
    /**
     * @param DocumentRoot $documentRoot The published path space, and the only translation
     * @param list<Root>   $roots        The source roots the site is composed from
     * @param string       $output       The directory a build writes to
     * @param string       $homeTitle    The heading over the landing page's cards, or empty
     *        for the site's name
     * @param string       $siteName     The site's display name, or empty for none
     * @param string       $versionLabel The version this build documents, or empty for none
     *
     * @throws InvalidArgumentException When two roots would publish at overlapping paths
     *
     * @mago-expect lint:excessive-parameter-list A deployment's constant values, each
     * named at the call site. Grouping the three identity strings into a type of their
     * own would add a type that exists only to shorten this signature, and every one of
     * these is read by a different collaborator.
     */
    public function __construct(
        public DocumentRoot $documentRoot,
        public array $roots = [],
        public string $output = 'build',
        public string $homeTitle = '',
        public string $siteName = '',
        public string $versionLabel = '',
    ) {
        self::assertExclusive($roots);
    }

    /**
     * Refuse a pair of roots that would claim one path.
     *
     * Two roots mounted at the same prefix, or one mounted beneath another, both
     * leave the owner of a path undecided, and iteration order would decide it.
     * A file published from one repository at a path belonging to another is
     * not a failure anyone can diagnose from the site, so it is refused here with
     * both mounts named.
     *
     * The iteration is over pairs rather than indices so that neither position is
     * read by number: a walk over the same list twice needs no bounds to be right.
     *
     * @param list<Root> $roots
     *
     * @throws InvalidArgumentException When two roots overlap
     */
    private static function assertExclusive(array $roots): void
    {
        foreach ($roots as $outer => $first) {
            foreach ($roots as $inner => $second) {
                if ($inner <= $outer || ! $first->overlaps($second)) {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'The roots "%s" and "%s" publish at overlapping mounts ("%s" and "%s"), so a path would have two owners.',
                    $first->path,
                    $second->path,
                    $first->mount,
                    $second->mount,
                ));
            }
        }
    }
}
