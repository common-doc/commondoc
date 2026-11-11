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
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\NormalizeHeadings\NormalizeHeadingsExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

#[CoversClass(ExtensionPluginManager::class)]
#[CoversMethod(ExtensionPluginManager::class, 'getDefaultExtensions')]
final class ExtensionPluginManagerTest extends TestCase
{
    #[Test]
    public function buildsAnExtensionThroughAFactoryThatReadsTheContainerConfig(): void
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

        $extension = $manager->get(TableExtension::class);

        static::assertInstanceOf(TableExtension::class, $extension);
        static::assertSame($extension, $manager->get(TableExtension::class));
    }

    #[Test]
    public function declaresTheAlwaysLoadedExtensionsInTheConstOrder(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class));

        static::assertSame(
            [
                TableExtension::class,
                StrikethroughExtension::class,
                TaskListExtension::class,
                HeadingPermalinkExtension::class,
                TableOfContentsExtension::class,
                NormalizeHeadingsExtension::class,
                ExternalLinkExtension::class,
                AutolinkExtension::class,
                DisallowedRawHtmlExtension::class,
            ],
            $manager->getDefaultExtensions(),
        );
    }

    #[Test]
    public function keepsTheAlwaysLoadedExtensionsWhenASectionIsSupplied(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'factories' => [
                'an-extension' => static fn(): StrikethroughExtension => new StrikethroughExtension(),
            ],
        ]);

        static::assertTrue($manager->has(TableExtension::class));
        static::assertTrue($manager->has('an-extension'));
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

        $manager->get('not-an-extension');
    }

    #[Test]
    public function reportsTheSectionNamesAfterTheAlwaysLoadedOnes(): void
    {
        $manager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), [
            'extension-settings' => ['enabled' => true],
            'invokables'         => [
                AttributesExtension::class => AttributesExtension::class,
            ],
            'factories'          => [
                'an-extension' => static fn(): SmartPunctExtension => new SmartPunctExtension(),
            ],
        ]);

        static::assertSame(
            [
                TableExtension::class,
                StrikethroughExtension::class,
                TaskListExtension::class,
                HeadingPermalinkExtension::class,
                TableOfContentsExtension::class,
                NormalizeHeadingsExtension::class,
                ExternalLinkExtension::class,
                AutolinkExtension::class,
                DisallowedRawHtmlExtension::class,
                AttributesExtension::class,
                'an-extension',
            ],
            $manager->getDefaultExtensions(),
        );
    }
}
