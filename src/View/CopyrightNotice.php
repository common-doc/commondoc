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

namespace CommonDoc\View;

use function sprintf;

/**
 * The copyright notice in the site's footer, as text.
 *
 * The site has been published since one year and is being built in another, so the notice
 * names one year or a span. The span is `2026 - 2027`, with the hyphen the rest of this
 * package uses rather than a dash, and only once the build's year has moved past the first.
 *
 * The year is the build's, not the reader's: a built page is a file, so a notice that
 * followed the reader's clock would need a script to say the same thing in a different year
 * on every visit. A rebuild rolls it forward, which is the only event that changes a site
 * the build writes.
 *
 * The site name is left out when a site states none, because a bare `@copyright 2026`
 * is still true and an invented name would not be.
 */
final readonly class CopyrightNotice
{
    public const int SINCE = 2026;

    private const string MARK = '@copyright';

    public function text(string $siteName, int $year): string
    {
        $years = $year > self::SINCE
            ? sprintf('%d - %d', self::SINCE, $year)
            : (string) self::SINCE;

        return '' === $siteName
            ? sprintf('%s %s', self::MARK, $years)
            : sprintf('%s %s %s', self::MARK, $siteName, $years);
    }
}
