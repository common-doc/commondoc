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
 * The deployment, in the two places it is described.
 *
 * Every project site differs from every other one in exactly two values, so the
 * root section holds only those. A custom domain and an organization site both
 * use `/`; a project site uses `/<repository>/`. Nothing here branches on which
 * of the three a site is, because the branch would be a copy of the base path.
 *
 * The page section holds the identity every page in a build shares. These are
 * blank for a single-repository build, which is the correct answer rather than a
 * placeholder: a page with no project name is a cosmetic problem, and inventing
 * one would be worse than leaving it empty.
 */

use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\PathType;
use CommonDoc\View\Page;

return [
    DocumentRoot::class => [
        // A project site publishes under its repository name, e.g. '/commondoc/'.
        'basePath' => '/',
        'pathType' => PathType::Directory,
    ],
    Page::class         => [
        'projectName'  => '',
        'projectSlug'  => '',
        'versionLabel' => '',
    ],
];
