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
use Laminas\ServiceManager\Exception\InvalidServiceException;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

use function array_map;

#[CoversClass(ExtensionPluginManager::class)]
#[CoversMethod(ExtensionPluginManager::class, '__construct')]
#[CoversMethod(ExtensionPluginManager::class, 'extensions')]
final class ExtensionPluginManagerTest extends TestCase
{
    #[Test]
    public function exposesNoExtensionsWhenTheConfigDeclaresNone(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'services' => [],
        ]);

        static::assertSame([], $manager->extensions());
    }

    #[Test]
    public function ignoresASectionThatIsNotAnArray(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'factories' => null,
        ]);

        static::assertSame([], $manager->extensions());
    }

    #[Test]
    public function ignoresSectionsThatDoNotDeclareExtensions(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'extension-settings' => [
                'enabled' => true,
            ],
        ]);

        static::assertSame([], $manager->extensions());
    }

    #[Test]
    public function keepsFactoryEntriesAheadOfInvokableEntriesWhenTheConfigDeclaresThemThatWay(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'factories'  => [
                TableExtension::class => static fn(): TableExtension => new TableExtension(),
            ],
            'invokables' => [
                StrikethroughExtension::class => StrikethroughExtension::class,
            ],
        ]);

        static::assertSame(
            [
                TableExtension::class,
                StrikethroughExtension::class,
            ],
            array_map(
                static fn(ExtensionInterface $extension): string => $extension::class,
                $manager->extensions(),
            ),
        );
    }

    #[Test]
    public function rejectsAnInstanceThatIsNotAnExtension(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'factories' => [
                'not-an-extension' => static fn(): stdClass => new stdClass(),
            ],
        ]);

        $this->expectException(InvalidServiceException::class);

        $manager->extensions();
    }

    #[Test]
    public function resolvesAnExtensionThroughAFactoryThatReadsTheContainerConfig(): void
    {
        $config = [
            'extension-settings' => ['enabled' => true],
        ];

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(static fn(string $id): mixed => 'config' === $id ? $config : null);

        $manager = new ExtensionPluginManager($container, [
            'factories' => [
                TableExtension::class => static function (ContainerInterface $pluginContainer): TableExtension {
                    static::assertSame(
                        ['enabled' => true],
                        $pluginContainer->get('config')['extension-settings'],
                    );

                    return new TableExtension();
                },
            ],
        ]);

        $extensions = $manager->extensions();

        static::assertCount(1, $extensions);
        static::assertInstanceOf(TableExtension::class, $extensions[0]);
    }

    #[Test]
    public function resolvesAnInvokableExtensionWithNoFactoryEntry(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'invokables' => [
                StrikethroughExtension::class => StrikethroughExtension::class,
            ],
        ]);

        $extensions = $manager->extensions();

        static::assertCount(1, $extensions);
        static::assertInstanceOf(StrikethroughExtension::class, $extensions[0]);
    }

    #[Test]
    public function returnsExtensionsInTheDeclaredOrder(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'invokables' => [
                TableExtension::class         => TableExtension::class,
                StrikethroughExtension::class => StrikethroughExtension::class,
                AutolinkExtension::class      => AutolinkExtension::class,
            ],
        ]);

        static::assertSame(
            [
                TableExtension::class,
                StrikethroughExtension::class,
                AutolinkExtension::class,
            ],
            array_map(
                static fn(ExtensionInterface $extension): string => $extension::class,
                $manager->extensions(),
            ),
        );
    }
}
