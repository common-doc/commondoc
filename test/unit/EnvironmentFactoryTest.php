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

use CommonDoc\Container\EnvironmentFactory;
use CommonDoc\Container\ExtensionPluginManager;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function array_map;

#[CoversClass(EnvironmentFactory::class)]
#[CoversMethod(EnvironmentFactory::class, '__invoke')]
final class EnvironmentFactoryTest extends TestCase
{
    #[Test]
    public function appliesTheConfiguredSection(): void
    {
        $environment = new EnvironmentFactory()(
            $this->containerWithConfig([EnvironmentInterface::class => ['html_input' => 'escape']]),
            EnvironmentInterface::class,
        );

        static::assertSame('escape', $environment->getConfiguration()->get('html_input'));
    }

    #[Test]
    public function fallsBackToTheDefaultsWhenTheConfigIsNotAnArray(): void
    {
        $environment = new EnvironmentFactory()(
            $this->containerWithConfig('not-an-array'),
            EnvironmentInterface::class,
        );

        static::assertSame('allow', $environment->getConfiguration()->get('html_input'));
    }

    #[Test]
    public function fallsBackToTheDefaultsWhenTheSectionIsMissing(): void
    {
        $environment = new EnvironmentFactory()(
            $this->containerWithConfig(['dependencies' => []]),
            EnvironmentInterface::class,
        );

        static::assertSame('allow', $environment->getConfiguration()->get('html_input'));
    }

    #[Test]
    public function registersCoreFirstThenThePluginManagerExtensionsInOrder(): void
    {
        $pluginManager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'invokables' => [
                TableExtension::class         => TableExtension::class,
                StrikethroughExtension::class => StrikethroughExtension::class,
            ],
        ]);

        $environment = new EnvironmentFactory()(
            $this->containerWithConfig([EnvironmentInterface::class => []], $pluginManager),
            EnvironmentInterface::class,
        );

        static::assertSame(
            [
                CommonMarkCoreExtension::class,
                TableExtension::class,
                StrikethroughExtension::class,
            ],
            array_map(
                static fn(ExtensionInterface $extension): string => $extension::class,
                [...$environment->getExtensions()],
            ),
        );
    }

    #[Test]
    public function registersTheCommonMarkCoreExtension(): void
    {
        $environment = new EnvironmentFactory()(
            $this->containerWithConfig([EnvironmentInterface::class => []]),
            EnvironmentInterface::class,
        );

        $extensions = [...$environment->getExtensions()];

        static::assertCount(1, $extensions);
        static::assertInstanceOf(CommonMarkCoreExtension::class, $extensions[0]);
    }

    private function containerWithConfig(
        mixed $config,
        ?ExtensionPluginManager $pluginManager = null,
    ): ContainerInterface {
        $pluginManager ??= new ExtensionPluginManager($this->createStub(ContainerInterface::class));

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    'config'                      => $config,
                    ExtensionPluginManager::class => $pluginManager,
                    default                       => null,
                },
            );

        return $container;
    }
}
