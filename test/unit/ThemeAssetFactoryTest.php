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

use CommonDoc\Container\ThemeAssetFactory;
use CommonDoc\View\Helper\ThemeAsset;
use Laminas\View\Helper\Asset;
use Laminas\View\Helper\BasePath;
use Laminas\View\HelperPluginManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The lookup's collaborators, resolved the way a build resolves them.
 *
 * Both come from the plugin manager, because that is where laminas view registers them: the
 * asset helper there carries the theme's merged map.
 */
#[CoversClass(ThemeAssetFactory::class)]
#[CoversMethod(ThemeAssetFactory::class, '__invoke')]
final class ThemeAssetFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheHelperFromThePluginManager(): void
    {
        static::assertInstanceOf(
            ThemeAsset::class,
            new ThemeAssetFactory()($this->container(), ThemeAsset::class),
        );
    }

    #[Test]
    public function resolvesTheAssetsTheMapDeclares(): void
    {
        $subject = new ThemeAssetFactory()($this->container(), ThemeAsset::class);

        static::assertSame('/repo/theme/webware/logo.svg', $subject(name: 'logo'));
    }

    private function container(): ContainerInterface
    {
        $helpers = new HelperPluginManager($this->createStub(ContainerInterface::class));
        $helpers->setService(Asset::class, new Asset(['logo' => '/theme/webware/logo.svg']));
        $helpers->setService(BasePath::class, new BasePath('/repo/'));

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    HelperPluginManager::class => $helpers,
                    default                    => self::fail("Unexpected service: {$id}"),
                },
            );

        return $container;
    }
}
