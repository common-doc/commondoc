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

namespace CommonDoc\View\Helper;

use CommonDoc\SiteConfig;
use Laminas\View\Helper\HelperInterface;

/**
 * The site's display name, for the layout to use as the base of the page title.
 *
 * It reads the site configuration because the layout renders without a page, so there is no
 * `Page` to take the name from. Returning an empty string when nothing is configured is what
 * lets the layout leave the title alone rather than emit a separator with nothing after it.
 *
 * @api
 */
final readonly class SiteName implements HelperInterface
{
    public function __construct(
        private SiteConfig $site,
    ) {}

    public function __invoke(): string
    {
        return $this->site->siteName;
    }
}
