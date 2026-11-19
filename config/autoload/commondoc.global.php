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
 * `roots` is the site's composition: each entry is a source directory and the
 * prefix it publishes at. The default is this repository's own documentation, so a
 * run with no arguments builds this site; a deployment states its own. Mounts must
 * be exclusive, and `SiteConfig` refuses a combination where one root's files would
 * be published at an address belonging to another.
 *
 * The identity values are blank for a single-repository build, which is the correct
 * answer rather than a placeholder: a page with no project name is a cosmetic
 * problem, and inventing one would be worse than leaving it empty.
 */

use CommonDoc\Output\PathType;
use CommonDoc\SiteConfig;

return [
    SiteConfig::class => [
        // A project site publishes under its repository name, e.g. '/commondoc/'.
        'basePath'     => '/',
        'pathType'     => PathType::Directory,
        'output'       => 'build',
        'roots'        => [
            ['path' => 'docs/v1', 'mount' => '/'],
        ],
        'projectName'  => '',
        'projectSlug'  => '',
        'versionLabel' => '',
    ],
];