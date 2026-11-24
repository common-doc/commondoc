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

use CommonDoc\View\Helper\ThemeAsset;
use Laminas\View\Helper\Asset;
use Laminas\View\Helper\BasePath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A theme asset name turned into an address: the three steps each caller would repeat.
 */
#[CoversClass(ThemeAsset::class)]
#[CoversMethod(ThemeAsset::class, '__construct')]
#[CoversMethod(ThemeAsset::class, '__invoke')]
final class ThemeAssetTest extends TestCase
{
    #[Test]
    public function answersNothingForANameNoThemeDeclares(): void
    {
        // A theme that carries no such asset is a normal state, not a failure.
        static::assertSame('', $this->helper()('logo'));
    }

    #[Test]
    public function leavesAnAbsoluteAddressAlone(): void
    {
        static::assertSame(
            'https://cdn.example/logo.svg',
            $this->helper(['logo' => 'https://cdn.example/logo.svg'], basePath: '/repo/')('logo'),
        );
    }

    #[Test]
    public function namesTheThemePathItWasGiven(): void
    {
        static::assertSame(
            '/theme/webware/img/logo.svg',
            $this->helper(['logo' => '/theme/webware/img/logo.svg'])('logo'),
        );
    }

    #[Test]
    public function putsARootRelativeAddressUnderTheBasePath(): void
    {
        static::assertSame(
            '/repo/theme/webware/img/logo.svg',
            $this->helper(['logo' => '/theme/webware/img/logo.svg'], basePath: '/repo/')('logo'),
        );
    }

    /**
     * @param array<non-empty-string, non-empty-string> $assets
     */
    private function helper(array $assets = [], string $basePath = '/'): ThemeAsset
    {
        return new ThemeAsset(
            asset   : new Asset($assets),
            basePath: new BasePath($basePath),
        );
    }
}
