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

use CommonDoc\Output\DocumentRoot;

/**
 * What a deployment is, read once from configuration.
 *
 * Two things are constant for one site and cannot be discovered during a run: the
 * address space it publishes into, and the identity every page in it shares. Both
 * arrive from configuration, and both are needed by more than one collaborator, so
 * reading them in each place that needs them would be one rule with several copies.
 * This type is that single read.
 *
 * It is constructed at boot and never varies within a run. Values that a command
 * line may override belong to the build request instead, because they arrive after
 * this object exists; keeping them apart is what stops a boot-time object from
 * carrying a value that can still change.
 *
 * The base path is deliberately not overridable. It describes how the site is
 * served, not how it is built: it is known before a run, and every address the
 * build emits is built from it, so a wrong value produces a site that looks right
 * on disk and resolves nothing once served.
 *
 * @api
 */
final readonly class SiteConfig
{
    /**
     * @param DocumentRoot $root         The address space, and the only translation
     * @param string       $projectName  The project's display name, or empty for none
     * @param string       $projectSlug  The project's identifier, or empty for none
     * @param string       $versionLabel The version this build documents, or empty for none
     */
    public function __construct(
        public DocumentRoot $root,
        public string $projectName = '',
        public string $projectSlug = '',
        public string $versionLabel = '',
    ) {}
}
