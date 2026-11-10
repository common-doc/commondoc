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

use CommonDoc\Container\ExtensionPluginManager;
use CommonDoc\Container\ExtensionPluginManagerFactory;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(ExtensionPluginManagerFactory::class)]
#[CoversMethod(ExtensionPluginManagerFactory::class, '__invoke')]
final class ExtensionPluginManagerFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheManagerFromTheSectionKeyedByItsClassName(): void
    {
        $manager = new ExtensionPluginManagerFactory()(
            $this->containerWithConfig([
                ExtensionPluginManager::class => [
                    'factories' => [
                        'an-extension' => static fn(): StrikethroughExtension => new StrikethroughExtension(),
                    ],
                ],
            ]),
            ExtensionPluginManager::class,
        );

        static::assertTrue($manager->has('an-extension'));
        static::assertInstanceOf(StrikethroughExtension::class, $manager->get('an-extension'));
    }

    #[Test]
    public function carriesTheDefaultsWhenTheConfigIsNotAnArray(): void
    {
        $manager = new ExtensionPluginManagerFactory()(
            $this->containerWithConfig('not-an-array'),
            ExtensionPluginManager::class,
        );

        static::assertTrue($manager->has(TableExtension::class));
        static::assertFalse($manager->has('an-extension'));
    }

    #[Test]
    public function carriesTheDefaultsWhenTheSectionIsMissing(): void
    {
        $manager = new ExtensionPluginManagerFactory()(
            $this->containerWithConfig(['dependencies' => []]),
            ExtensionPluginManager::class,
        );

        static::assertTrue($manager->has(TableExtension::class));
        static::assertFalse($manager->has('an-extension'));
    }

    #[Test]
    public function carriesTheDefaultsWhenTheSectionIsNotAnArray(): void
    {
        $manager = new ExtensionPluginManagerFactory()(
            $this->containerWithConfig([ExtensionPluginManager::class => 'not-an-array']),
            ExtensionPluginManager::class,
        );

        static::assertTrue($manager->has(TableExtension::class));
        static::assertFalse($manager->has('an-extension'));
    }

    private function containerWithConfig(mixed $config): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(static fn(string $id): mixed => 'config' === $id ? $config : null);

        return $container;
    }
}
