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

use CommonDoc\Container\HomeTitleFactory;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\SiteConfig;
use CommonDoc\View\Helper\HomeTitle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The heading over the landing page's cards, which is the site's to state.
 *
 * A composed site publishes no document at its root, so the heading is written by this package
 * and would otherwise be a word a site cannot change without replacing the template.
 */
#[CoversClass(HomeTitle::class)]
#[CoversClass(HomeTitleFactory::class)]
#[CoversMethod(HomeTitle::class, '__construct')]
#[CoversMethod(HomeTitle::class, '__invoke')]
#[CoversMethod(HomeTitleFactory::class, '__invoke')]
final class HomeTitleTest extends TestCase
{
    #[Test]
    public function answersNothingWhenTheSiteStatesNothingAtAll(): void
    {
        // The template renders no heading rather than an empty one.
        static::assertSame('', new HomeTitle(site: $this->site())());
    }

    #[Test]
    public function answersTheHeadingTheSiteStates(): void
    {
        static::assertSame(
            'Start here',
            new HomeTitle(site: $this->site(homeTitle: 'Start here'))(),
        );
    }

    #[Test]
    public function buildsFromTheSiteTheContainerHolds(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($this->site(homeTitle: 'Start here'));

        static::assertSame(
            'Start here',
            new HomeTitleFactory()($container, HomeTitle::class)(),
        );
    }

    #[Test]
    public function fallsBackToTheSitesName(): void
    {
        static::assertSame('Webware', new HomeTitle(site: $this->site(siteName: 'Webware'))());
    }

    private function site(string $siteName = '', string $homeTitle = ''): SiteConfig
    {
        return new SiteConfig(
            documentRoot: new DocumentRoot('/'),
            siteName    : $siteName,
            homeTitle   : $homeTitle,
        );
    }
}
