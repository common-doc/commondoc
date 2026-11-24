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

use CommonDoc\View\Helper\Favicon;
use CommonDoc\View\Helper\ThemeAsset;
use Laminas\Escaper\Escaper;
use Laminas\View\Helper\Asset;
use Laminas\View\Helper\BasePath;
use Laminas\View\Helper\Doctype;
use Laminas\View\Helper\HeadLink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The head's favicon link, which appears only when a theme declares one.
 *
 * The link is queued on `headLink` rather than written by the helper, so the assertion is on
 * what that helper renders once the favicon has been asked for.
 */
#[CoversClass(Favicon::class)]
#[CoversMethod(Favicon::class, '__construct')]
#[CoversMethod(Favicon::class, '__invoke')]
final class FaviconTest extends TestCase
{
    #[Test]
    public function asksForTheNameItIsGiven(): void
    {
        static::assertStringContainsString(
            'href="&#x2F;theme&#x2F;webware&#x2F;img&#x2F;mark.svg"',
            $this->favicon(['mark' => '/theme/webware/img/mark.svg'])(name: 'mark'),
        );
    }

    #[Test]
    public function leavesTheHeadAloneWhenNoThemeDeclaresOne(): void
    {
        static::assertSame('', $this->favicon()());
    }

    #[Test]
    public function queuesTheIconBeneathTheBasePath(): void
    {
        static::assertStringContainsString(
            'href="&#x2F;repo&#x2F;theme&#x2F;webware&#x2F;favicon.svg"',
            $this->favicon(['favicon' => '/theme/webware/favicon.svg'], basePath: '/repo/')(),
        );
    }

    #[Test]
    public function queuesTheThemesIconAsACanonicalLink(): void
    {
        static::assertStringContainsString(
            '<link href="&#x2F;theme&#x2F;webware&#x2F;favicon.svg" rel="icon">',
            $this->favicon(['favicon' => '/theme/webware/favicon.svg'])(),
        );
    }

    /**
     * @param array<non-empty-string, non-empty-string> $assets
     */
    private function favicon(array $assets = [], string $basePath = '/'): callable
    {
        $headLink = new HeadLink(new Escaper(), new Doctype());
        $favicon  = new Favicon(
            themeAsset: new ThemeAsset(
                asset   : new Asset($assets),
                basePath: new BasePath($basePath),
            ),
            headLink  : $headLink,
        );

        return static function (string $name = 'favicon') use ($favicon, $headLink): string {
            $favicon($name);

            return (string) $headLink;
        };
    }
}
