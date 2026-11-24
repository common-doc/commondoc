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

use Laminas\View\Exception\ExceptionInterface;
use Laminas\View\Helper\HeadLink;
use Laminas\View\Helper\HelperInterface;

/**
 * The theme's favicon as a `link` in the document head.
 *
 * It belongs to the theme for the same reason the logo does: a favicon is a picture a theme
 * carries, so a theme declares an asset named `favicon` and the active theme's value is laid
 * over the `default` theme's. A theme that declares none adds no link, which is why the
 * lookup answers rather than refuses.
 *
 * The link is queued on `headLink` instead of being written here, so the head keeps one order
 * for every resource a template adds and a page template can still append its own. That is
 * why this helper is called before the layout renders `headLink`, and why it returns nothing.
 *
 * No `type` is written. A browser sniffs the format, and the theme may serve an SVG, an ICO or
 * a PNG without telling this package which one it chose.
 *
 * The name is a parameter for the same reason the brand's is: a theme that declares its icon
 * under another name passes it, and the layout stays free of the decision.
 */
final readonly class Favicon implements HelperInterface
{
    public function __construct(
        private ThemeAsset $themeAsset,
        private HeadLink $headLink,
    ) {}

    /**
     * @param non-empty-string $name The asset name a theme declares its favicon under
     *
     * @throws ExceptionInterface When no base path is configured, so an address cannot be composed
     */
    public function __invoke(string $name = 'favicon'): void
    {
        $href = $this->themeAsset->__invoke($name);

        if ('' === $href) {
            return;
        }

        $this->headLink->__invoke([
            'rel'  => 'icon',
            'href' => $href,
        ]);
    }
}
