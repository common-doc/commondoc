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

namespace CommonDocTest;

use CommonDoc\Output\DocumentRoot;
use CommonDoc\SiteConfig;
use CommonDoc\View\Helper\Brand;
use CommonDoc\View\Helper\ThemeAsset;
use Laminas\Escaper\Escaper;
use Laminas\View\Helper\Asset;
use Laminas\View\Helper\BasePath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The navbar's brand: the theme's logo when it declares one, else the site's name.
 *
 * The asset helper is built the way the theme factory builds it, from a map whose values are
 * already theme paths, so what the brand does with a path is what a build gets. Addresses are
 * escaped in attribute context, which is why a slash reads `&#x2F;` here.
 */
#[CoversClass(Brand::class)]
#[CoversMethod(Brand::class, '__construct')]
#[CoversMethod(Brand::class, '__invoke')]
final class BrandTest extends TestCase
{
    #[Test]
    public function asksForTheNameItIsGiven(): void
    {
        static::assertStringContainsString(
            'src="&#x2F;theme&#x2F;webware&#x2F;img&#x2F;mark.svg"',
            $this->brand(['mark' => '/theme/webware/img/mark.svg'], siteName: 'Webware')(name: 'mark'),
        );
    }

    #[Test]
    public function drawsTheLogoTheThemeDeclares(): void
    {
        static::assertStringContainsString(
            '<img src="&#x2F;theme&#x2F;webware&#x2F;img&#x2F;logo.svg"',
            $this->brand(['logo' => '/theme/webware/img/logo.svg'], siteName: 'Webware')(),
        );
    }

    #[Test]
    public function escapesTheNameIntoTheAltAndTheText(): void
    {
        static::assertStringContainsString(
            'alt="A &lt; B"',
            $this->brand(['logo' => '/theme/webware/img/logo.svg'], siteName: 'A < B')(),
        );
        static::assertStringContainsString(
            '>A &lt; B</a>',
            $this->brand(siteName: 'A < B')(),
        );
    }

    #[Test]
    public function fallsBackToTheSiteNameWhenTheThemeCarriesNoLogo(): void
    {
        $brand = $this->brand(siteName: 'Webware')();

        static::assertStringContainsString('>Webware</a>', $brand);
        static::assertStringNotContainsString('<img', $brand);
    }

    #[Test]
    public function leavesAnAbsoluteLogoAlone(): void
    {
        static::assertStringContainsString(
            'src="https&#x3A;&#x2F;&#x2F;cdn.example&#x2F;logo.svg"',
            $this->brand(
                ['logo' => 'https://cdn.example/logo.svg'],
                siteName: 'Webware',
                basePath: '/repo/',
            )(),
        );
    }

    #[Test]
    public function linksHomeBeneathTheBasePath(): void
    {
        static::assertStringContainsString(
            'href="&#x2F;repo&#x2F;"',
            $this->brand(
                siteName: 'Webware',
                basePath: '/repo/',
            )(),
        );
    }

    #[Test]
    public function namesTheSiteInTheAltText(): void
    {
        static::assertStringContainsString(
            'alt="Webware"',
            $this->brand(['logo' => '/theme/webware/img/logo.svg'], siteName: 'Webware')(),
        );
    }

    #[Test]
    public function putsARootRelativeLogoUnderTheBasePath(): void
    {
        static::assertStringContainsString(
            'src="&#x2F;repo&#x2F;theme&#x2F;webware&#x2F;img&#x2F;logo.svg"',
            $this->brand(
                ['logo' => '/theme/webware/img/logo.svg'],
                siteName: 'Webware',
                basePath: '/repo/',
            )(),
        );
    }

    #[Test]
    public function rendersNothingWhenThereIsNoLogoAndNoName(): void
    {
        // A link home with nothing inside it is a link a reader cannot identify.
        static::assertSame('', $this->brand()());
    }

    /**
     * @param array<non-empty-string, non-empty-string> $assets
     */
    private function brand(
        array $assets = [],
        string $siteName = '',
        string $basePath = '/',
    ): Brand {
        return new Brand(
            site      : new SiteConfig(
                documentRoot: new DocumentRoot($basePath),
                siteName    : $siteName,
            ),
            themeAsset: new ThemeAsset(
                asset   : new Asset($assets),
                basePath: new BasePath($basePath),
            ),
            basePath  : new BasePath($basePath),
            escaper   : new Escaper(),
        );
    }
}
