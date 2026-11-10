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
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\ExtensionInterface;
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

use function array_map;

#[CoversClass(EnvironmentFactory::class)]
#[CoversMethod(EnvironmentFactory::class, '__invoke')]
final class EnvironmentFactoryTest extends TestCase
{
    /**
     * @return list<class-string<ExtensionInterface>>
     */
    private static function defaultExtensionClasses(): array
    {
        return [
            TableExtension::class,
            StrikethroughExtension::class,
            TaskListExtension::class,
            HeadingPermalinkExtension::class,
            TableOfContentsExtension::class,
            NormalizeHeadingsExtension::class,
            ExternalLinkExtension::class,
            AutolinkExtension::class,
            DisallowedRawHtmlExtension::class,
        ];
    }

    /**
     * @return list<string>
     */
    private static function extensionClasses(EnvironmentInterface $environment): array
    {
        return array_map(
            static fn(ExtensionInterface $extension): string => $extension::class,
            [...$environment->getExtensions()],
        );
    }

    #[Test]
    public function addsTheSectionExtensionsAfterTheAlwaysLoadedOnes(): void
    {
        $section = [
            'invokables' => [
                AttributesExtension::class => AttributesExtension::class,
                SmartPunctExtension::class => SmartPunctExtension::class,
            ],
        ];

        $environment = new EnvironmentFactory()(
            $this->containerWithConfig([ExtensionPluginManager::class => $section], $section),
            EnvironmentInterface::class,
        );

        static::assertSame(
            [
                CommonMarkCoreExtension::class,
                ...self::defaultExtensionClasses(),
                AttributesExtension::class,
                SmartPunctExtension::class,
            ],
            self::extensionClasses($environment),
        );
    }

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
    public function keepsTheSectionDeclarationOrderAcrossFactoriesAndInvokables(): void
    {
        $section = [
            'invokables' => [
                AttributesExtension::class => AttributesExtension::class,
            ],
            'factories'  => [
                SmartPunctExtension::class => static fn(): SmartPunctExtension => new SmartPunctExtension(),
            ],
        ];

        $environment = new EnvironmentFactory()(
            $this->containerWithConfig([ExtensionPluginManager::class => $section], $section),
            EnvironmentInterface::class,
        );

        static::assertSame(
            [
                CommonMarkCoreExtension::class,
                ...self::defaultExtensionClasses(),
                AttributesExtension::class,
                SmartPunctExtension::class,
            ],
            self::extensionClasses($environment),
        );
    }

    #[Test]
    public function registersCoreFirstThenTheAlwaysLoadedExtensions(): void
    {
        $environment = new EnvironmentFactory()(
            $this->containerWithConfig([EnvironmentInterface::class => []]),
            EnvironmentInterface::class,
        );

        static::assertSame(
            [
                CommonMarkCoreExtension::class,
                ...self::defaultExtensionClasses(),
            ],
            self::extensionClasses($environment),
        );
    }

    #[Test]
    public function skipsSectionEntriesThatDeclareNoExtensions(): void
    {
        $environment = new EnvironmentFactory()(
            $this->containerWithConfig(
                [
                    ExtensionPluginManager::class => [
                        'extension-settings' => ['enabled' => true],
                        'factories'          => 'not-an-array',
                        'invokables'         => [
                            AttributesExtension::class => AttributesExtension::class,
                        ],
                    ],
                ],
                [
                    'invokables' => [
                        AttributesExtension::class => AttributesExtension::class,
                    ],
                ],
            ),
            EnvironmentInterface::class,
        );

        static::assertSame(
            [
                CommonMarkCoreExtension::class,
                ...self::defaultExtensionClasses(),
                AttributesExtension::class,
            ],
            self::extensionClasses($environment),
        );
    }

    /**
     * @param array<string, mixed> $managerSection
     */
    private function containerWithConfig(
        mixed $config,
        array $managerSection = [],
    ): ContainerInterface {
        $pluginManager = new ExtensionPluginManager($this->createStub(ContainerInterface::class), $managerSection);

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
