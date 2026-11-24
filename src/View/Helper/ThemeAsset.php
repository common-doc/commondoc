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
use Laminas\View\Helper\Asset;
use Laminas\View\Helper\BasePath;
use Laminas\View\Helper\HelperInterface;

use function str_starts_with;

/**
 * A theme asset as an address the browser can fetch, or nothing.
 *
 * It exists because the answer takes three steps that every caller would otherwise repeat:
 * ask the theme's map, treat an undeclared name as "this theme carries no such asset" rather
 * than a failure, and put a root-relative answer under the base path.
 *
 * The base path is applied here rather than by the theme, because the asset map composes a
 * theme path (`/theme/<theme>/...`) and knows nothing about where the site is served. A value
 * that is already absolute is passed through untouched, since prefixing it would produce
 * `/repo/https://...`.
 *
 * The lookup is made through the asset helper rather than from configuration, so the theme
 * merge stays in one place: the `default` theme's values are laid down first and the active
 * theme's over them, which is what lets a theme override an asset and lets a theme that
 * declares none fall back to the one it inherits.
 */
final readonly class ThemeAsset implements HelperInterface
{
    public function __construct(
        private Asset $asset,
        private BasePath $basePath,
    ) {}

    /**
     * @param non-empty-string $name The name a theme declares the asset under
     *
     * @return string The address, or an empty string when no theme declares the name
     *
     * @throws ExceptionInterface When no base path is configured, so an address cannot be composed
     */
    public function __invoke(string $name): string
    {
        try {
            $url = $this->asset->__invoke($name);
        } catch (ExceptionInterface) {
            return '';
        }

        return str_starts_with($url, '/') ? $this->basePath->__invoke($url) : $url;
    }
}
