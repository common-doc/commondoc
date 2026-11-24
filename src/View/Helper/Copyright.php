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
use CommonDoc\View\CopyrightNotice;
use Laminas\View\Helper\HelperInterface;

use function date;

/**
 * The footer's copyright notice.
 *
 * It reads the site configuration because the footer belongs to the site rather than to a
 * page: a page says which component and version it holds, and the notice says whose site
 * this is. The year is read once per render, from the clock of the machine building the
 * site, which is what makes a rebuild roll the notice forward.
 *
 * A theme that wants a different notice replaces this helper, the same way it replaces the
 * menus: nothing in the template composes the words.
 *
 * @api
 */
final readonly class Copyright implements HelperInterface
{
    public function __construct(
        private SiteConfig $site,
        private CopyrightNotice $notice,
    ) {}

    public function __invoke(): string
    {
        return $this->notice->text(
            projectName: $this->site->projectName,
            year       : (int) date('Y'),
        );
    }
}
