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

use CommonDoc\Container\BrandFactory;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\SiteConfig;
use CommonDoc\View\Helper\Brand;
use CommonDoc\View\Helper\ThemeAsset;
use Laminas\Escaper\Escaper;
use Laminas\Escaper\EscaperInterface;
use Laminas\View\Helper\Asset;
use Laminas\View\Helper\BasePath;
use Laminas\View\HelperPluginManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The brand's collaborators, resolved the way a build resolves them.
 *
 * The asset and base path helpers come from the plugin manager, because that is where they
 * are registered: neither has a service of its own.
 */
#[CoversClass(BrandFactory::class)]
#[CoversMethod(BrandFactory::class, '__invoke')]
final class BrandFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheHelperFromThePluginManager(): void
    {
        static::assertInstanceOf(Brand::class, new BrandFactory()($this->container(), Brand::class));
    }

    #[Test]
    public function rendersTheLogoTheAssetHelperCarries(): void
    {
        $subject = new BrandFactory()($this->container(), Brand::class);

        static::assertStringContainsString(
            '<img src="&#x2F;theme&#x2F;webware&#x2F;logo.svg"',
            $subject->__invoke(),
        );
    }

    private function container(): ContainerInterface
    {
        $helpers = new HelperPluginManager($this->createStub(ContainerInterface::class));
        $helpers->setService(Asset::class, new Asset(['logo' => '/theme/webware/logo.svg']));
        $helpers->setService(BasePath::class, new BasePath('/'));
        $helpers->setService(
            ThemeAsset::class,
            new ThemeAsset(
                asset   : new Asset(['logo' => '/theme/webware/logo.svg']),
                basePath: new BasePath('/'),
            ),
        );

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    HelperPluginManager::class => $helpers,
                    SiteConfig::class          => new SiteConfig(
                        documentRoot: new DocumentRoot('/'),
                        siteName    : 'Webware',
                    ),
                    Escaper::class             => new Escaper(),
                    EscaperInterface::class    => new Escaper(),
                    default                    => self::fail("Unexpected service: {$id}"),
                },
            );

        return $container;
    }
}
