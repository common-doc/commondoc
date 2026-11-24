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
use Laminas\Escaper\EscaperInterface;
use Laminas\View\Exception\ExceptionInterface;
use Laminas\View\Helper\BasePath;
use Laminas\View\Helper\HelperInterface;

use function sprintf;

/**
 * What the navbar shows at its top left, as one link home.
 *
 * The logo belongs to the theme, because that is the only place a logo can be replaced per
 * theme: a theme declares an asset named `logo` and the active theme's value is laid over
 * the `default` theme's, so a site overrides the picture by choosing a theme rather than by
 * setting a value this package would have to read from somewhere else.
 *
 * An undeclared name is the answer "this theme carries no logo", which is not a failure, so
 * the lookup is the `themeAsset` helper's job and its empty answer is the fallback to the
 * site's name. The name is a parameter for the same reason `componentMenu` takes its label: a
 * theme that wants a different name passes it, and the layout stays free of the decision.
 *
 * The alt text is the site's name, which is also the text shown when there is no logo, so a
 * reader who cannot see the picture is told whose site this is. A site that states no name
 * gets no brand at all rather than a link with nothing in it.
 */
final readonly class Brand implements HelperInterface
{
    public function __construct(
        private SiteConfig $site,
        private ThemeAsset $themeAsset,
        private BasePath $basePath,
        private EscaperInterface $escaper,
    ) {}

    /**
     * @param non-empty-string $name The asset name a theme declares its logo under
     *
     * @throws ExceptionInterface When no base path is configured, so an address cannot be composed
     */
    public function __invoke(string $name = 'logo'): string
    {
        $href  = $this->escaper->escapeHtmlAttr($this->basePath->__invoke('/'));
        $label = $this->site->siteName;
        $logo  = $this->themeAsset->__invoke($name);

        if ('' !== $logo) {
            return sprintf(
                '<a class="navbar-brand" href="%s"><img src="%s" alt="%s" class="d-inline-block align-text-top" style="height:2rem;width:auto"></a>',
                $href,
                $this->escaper->escapeHtmlAttr($logo),
                $this->escaper->escapeHtml($label),
            );
        }

        if ('' === $label) {
            return '';
        }

        return sprintf(
            '<a class="navbar-brand" href="%s">%s</a>',
            $href,
            $this->escaper->escapeHtml($label),
        );
    }
}
