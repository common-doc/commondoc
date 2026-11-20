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

/*
 * The site, in one section keyed by the type that reads it.
 *
 * A custom domain and an organization site both publish at `/`; a project site
 * publishes at `/<repository>/`. Nothing here branches on which of the three a site
 * is, because the branch would be a copy of the base path.
 *
 * The base path is configuration only and cannot be passed on a command line. It
 * describes how the site is served rather than how it is built, and every address
 * the build emits is made from it, so a wrong value produces a site that looks
 * right on disk and resolves nothing once served.
 *
 * It is stated once, as `view_helper_config.base_path`, because that is the value
 * the `BasePath` helper answers with when a template composes a link. The site
 * section does not state it: one value decides both the addresses a build writes
 * and the addresses a template emits, so the two cannot drift.
 *
 * `roots` is the site's composition: each entry names a repository and the prefix it
 * publishes at. An entry may also name its own `target`, which is where that
 * repository keeps its documentation; the site-level `target` is the answer for every
 * entry that states none. The default here is this repository's own documentation, so
 * a run with no arguments builds this site; a deployment states its own. Mounts must
 * be exclusive, and `SiteConfig` refuses a combination where one root's files would
 * be published at an address belonging to another.
 *
 * The target is why nothing here hard-codes `docs/v1`. A repository migrating from
 * DocBook keeps its sources in `docs/book`, and that is a value the operator supplies
 * rather than a variation this package decides.
 *
 * The identity values are blank for a single-repository build, which is the correct
 * answer rather than a placeholder: a page with no project name is a cosmetic
 * problem, and inventing one would be worse than leaving it empty.
 */

use CommonDoc\Input\Target;
use CommonDoc\Output\PathType;
use CommonDoc\SiteConfig;

return [
    // A project site publishes under its repository name, e.g. '/commondoc/'.
    'view_helper_config' => [
        'base_path' => '/',
    ],
    SiteConfig::class => [
        'pathType'     => PathType::Directory,
        'output'       => 'build',
        // Where a repository keeps its documentation, relative to its root. A composed
        // site states it once here and overrides it per entry when one repository
        // differs.
        'target'       => Target::DEFAULT,
        'roots'        => [
            ['repository' => '.', 'mount' => '/'],
        ],
        'projectName'  => '',
        'projectSlug'  => '',
        'versionLabel' => '',
    ],
];