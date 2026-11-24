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

use CommonDoc\Container\CopyrightFactory;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\SiteConfig;
use CommonDoc\View\CopyrightNotice;
use CommonDoc\View\Helper\Copyright;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function date;

/**
 * The footer's notice, which belongs to the site rather than to a page.
 *
 * The year is the build's, read from the clock of the machine writing the site, so the
 * assertions state the current year rather than pinning one.
 */
#[CoversClass(Copyright::class)]
#[CoversClass(CopyrightFactory::class)]
#[CoversMethod(Copyright::class, '__construct')]
#[CoversMethod(Copyright::class, '__invoke')]
#[CoversMethod(CopyrightFactory::class, '__invoke')]
final class CopyrightTest extends TestCase
{
    #[Test]
    public function buildsFromTheSiteTheContainerHolds(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($this->site('Webware'));

        static::assertSame(
            '@copyright Webware ' . date('Y'),
            new CopyrightFactory()($container, Copyright::class)(),
        );
    }

    #[Test]
    public function leavesTheNameOutWhenTheSiteStatesNone(): void
    {
        static::assertSame(
            '@copyright ' . date('Y'),
            new Copyright(
                site  : $this->site(),
                notice: new CopyrightNotice(),
            )(),
        );
    }

    #[Test]
    public function namesTheConfiguredSiteAndTheYearBeingBuilt(): void
    {
        static::assertSame(
            '@copyright Webware ' . date('Y'),
            new Copyright(
                site  : $this->site('Webware'),
                notice: new CopyrightNotice(),
            )(),
        );
    }

    private function site(string $siteName = ''): SiteConfig
    {
        return new SiteConfig(
            documentRoot: new DocumentRoot('/'),
            siteName    : $siteName,
        );
    }
}
