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
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function array_map;

#[CoversClass(ExtensionPluginManagerFactory::class)]
#[CoversMethod(ExtensionPluginManagerFactory::class, '__invoke')]
final class ExtensionPluginManagerFactoryTest extends TestCase
{
    #[Test]
    public function buildsAnEmptyManagerWhenTheConfigIsNotAnArray(): void
    {
        $manager = new ExtensionPluginManagerFactory()(
            $this->containerWithConfig('not-an-array'),
            ExtensionPluginManager::class,
        );

        static::assertSame([], $manager->extensions());
    }

    #[Test]
    public function buildsAnEmptyManagerWhenTheSectionIsMissing(): void
    {
        $manager = new ExtensionPluginManagerFactory()(
            $this->containerWithConfig(['dependencies' => []]),
            ExtensionPluginManager::class,
        );

        static::assertSame([], $manager->extensions());
    }

    #[Test]
    public function buildsAnEmptyManagerWhenTheSectionIsNotAnArray(): void
    {
        $manager = new ExtensionPluginManagerFactory()(
            $this->containerWithConfig([ExtensionPluginManager::class => 'not-an-array']),
            ExtensionPluginManager::class,
        );

        static::assertSame([], $manager->extensions());
    }

    #[Test]
    public function buildsTheManagerFromTheSectionKeyedByItsClassName(): void
    {
        $manager = new ExtensionPluginManagerFactory()(
            $this->containerWithConfig([
                ExtensionPluginManager::class => [
                    'invokables' => [
                        StrikethroughExtension::class => StrikethroughExtension::class,
                    ],
                ],
            ]),
            ExtensionPluginManager::class,
        );

        static::assertSame(
            [StrikethroughExtension::class],
            array_map(
                static fn(ExtensionInterface $extension): string => $extension::class,
                $manager->extensions(),
            ),
        );
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
