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
 * The heading over the landing page's cards.
 *
 * A composed site publishes no document at its root, so the home page is generated and its
 * heading is the site's own to state. `homeTitle` says it, and a site that states none gets
 * the site's name, because a landing page headed with its own name is right more often than
 * one with no heading at all. A site that states neither gets an empty string, and the
 * template renders no heading rather than an empty one.
 *
 * @api
 */
final readonly class HomeTitle implements HelperInterface
{
    public function __construct(
        private SiteConfig $site,
    ) {}

    public function __invoke(): string
    {
        return '' === $this->site->homeTitle ? $this->site->siteName : $this->site->homeTitle;
    }
}
