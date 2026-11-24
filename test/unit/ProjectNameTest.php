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

use CommonDoc\Container\ProjectNameFactory;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\SiteConfig;
use CommonDoc\View\Helper\ProjectName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The base segment of every page title.
 *
 * The layout renders without a page, so the name comes from the site configuration rather
 * than from the document being rendered.
 */
#[CoversClass(ProjectName::class)]
#[CoversClass(ProjectNameFactory::class)]
#[CoversMethod(ProjectName::class, '__construct')]
#[CoversMethod(ProjectName::class, '__invoke')]
#[CoversMethod(ProjectNameFactory::class, '__invoke')]
final class ProjectNameTest extends TestCase
{
    #[Test]
    public function answersNothingWhenTheSiteStatesNoName(): void
    {
        // An empty answer is what lets the layout leave the title alone rather than emit a
        // separator with nothing after it.
        static::assertSame('', new ProjectName($this->site())());
    }

    #[Test]
    public function answersTheConfiguredName(): void
    {
        static::assertSame('Webware', new ProjectName($this->site('Webware'))());
    }

    #[Test]
    public function buildsFromTheSiteTheContainerHolds(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($this->site('Webware'));

        static::assertSame(
            'Webware',
            new ProjectNameFactory()($container, ProjectName::class)(),
        );
    }

    private function site(string $projectName = ''): SiteConfig
    {
        return new SiteConfig(
            documentRoot: new DocumentRoot('/'),
            projectName : $projectName,
        );
    }
}
