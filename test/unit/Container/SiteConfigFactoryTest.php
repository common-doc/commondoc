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

namespace CommonDocTest\Container;

use CommonDoc\Container\SiteConfigFactory;
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Output\PathType;
use CommonDoc\SiteConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The site is read from one section, and a section that does not fit is replaced
 * rather than refused.
 *
 * Both halves matter. Reading it once is what stops the path space and the
 * identity from being described in two places that can drift. Falling back is what
 * keeps a deployment with one misspelled key buildable, while the value that
 * genuinely cannot work is still refused by `DocumentRoot`, which is where the
 * statement about a base path belongs.
 */
#[CoversClass(SiteConfigFactory::class)]
#[CoversMethod(SiteConfigFactory::class, '__invoke')]
final class SiteConfigFactoryTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $section
     * @param array<array-key, mixed> $viewHelperConfig
     */
    private static function config(array $section, array $viewHelperConfig = ['base_path' => '/']): array
    {
        return [
            SiteConfig::class    => $section,
            'view_helper_config' => $viewHelperConfig,
        ];
    }

    /**
     * @param mixed $config Whatever the container should serve as configuration
     */
    private static function container(mixed $config): ContainerInterface
    {
        $container = self::createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => 'config' === $id ? $config : null,
            );

        return $container;
    }

    /**
     * @param array<array-key, mixed> $section
     * @param array<array-key, mixed> $viewHelperConfig
     */
    private static function site(
        array $section = [],
        array $viewHelperConfig = ['base_path' => '/'],
    ): SiteConfig {
        return new SiteConfigFactory()(
            self::container(self::config(
                section         : [
                    'pathType' => PathType::Directory,
                    ...$section,
                ],
                viewHelperConfig: $viewHelperConfig,
            )),
            SiteConfig::class,
        );
    }

    #[Test]
    public function defaultsToTheCustomDomainDeployment(): void
    {
        $site = self::site();

        static::assertSame('/', $site->documentRoot->basePath);
        static::assertSame(PathType::Directory, $site->documentRoot->pathType);
        static::assertSame('guide/setup/', $site->documentRoot->pathOf('guide/setup.md'));
    }

    #[Test]
    public function defaultsToThisRepositorysOwnSources(): void
    {
        // A library default cannot know a deployment's layout, so it states the one
        // layout that is true of this repository. A run with no arguments then
        // builds the configured site, which is the common case.
        $site = self::site();

        static::assertSame('build', $site->output);
        static::assertCount(1, $site->roots);
        static::assertSame('docs/v1', $site->roots[0]->path);
        static::assertSame('/', $site->roots[0]->mount);
    }

    #[Test]
    public function dropsARootEntryThatCannotNameADirectory(): void
    {
        // A typo costs its own entry rather than every entry, for the same reason a
        // wrong key costs only its own value.
        $site = self::site([
            'roots' => [
                ['path' => 'docs/v1', 'mount' => '/'],
                ['mount' => '/broken/'],
                ['path' => '', 'mount' => '/empty/'],
                'not an entry',
                ['path' => 'other', 'mount' => 42],
            ],
        ]);

        static::assertCount(1, $site->roots);
        static::assertSame('docs/v1', $site->roots[0]->path);
    }

    #[Test]
    public function fallsBackToTheDefaultRootsWhenNoneSurvives(): void
    {
        $site = self::site(['roots' => ['not an entry']]);

        static::assertSame('docs/v1', $site->roots[0]->path);
    }

    #[Test]
    public function fallsBackWhenTheConfigServiceIsMissingEntirely(): void
    {
        // The container is asked for 'config' and may not have it; a stub that
        // returns null stands in for that case.
        $container = self::createStub(ContainerInterface::class);
        $container->method('get')->willReturn(null);

        $site = new SiteConfigFactory()($container, SiteConfig::class);

        static::assertSame('/', $site->documentRoot->basePath);
        static::assertSame(PathType::Directory, $site->documentRoot->pathType);
    }

    #[Test]
    public function fallsBackWhenTheConfigServiceIsNotAnArrayAtAll(): void
    {
        // A string is the case the guard exists for: reading an offset from one is
        // not a missing key, it is a TypeError.
        $container = self::createStub(ContainerInterface::class);
        $container->method('get')->willReturn('not config');

        $site = new SiteConfigFactory()($container, SiteConfig::class);

        static::assertSame('/', $site->documentRoot->basePath);
    }

    #[Test]
    public function fallsBackWhenTheSectionIsMalformed(): void
    {
        // A deployment with one wrong key should still build, so a section that
        // does not match the shape is replaced rather than refused.
        $site = new SiteConfigFactory()(
            self::container(self::config([], ['base_path' => 42])),
            SiteConfig::class,
        );

        static::assertSame('/', $site->documentRoot->basePath);
    }

    #[Test]
    public function leavesTheIdentityBlankWhenNoneIsConfigured(): void
    {
        $site = self::site();

        static::assertSame('', $site->projectName);
        static::assertSame('', $site->projectSlug);
        static::assertSame('', $site->versionLabel);
    }

    #[Test]
    public function readsThePathSpaceFromTheViewHelperConfigAndTheIdentityFromTheSiteSection(): void
    {
        $site = self::site(
            section         : [
                'pathType'     => PathType::File,
                'projectName'  => 'CommonDoc',
                'projectSlug'  => 'commondoc',
                'versionLabel' => '1.0',
            ],
            viewHelperConfig: ['base_path' => '/repo/'],
        );

        static::assertSame('/repo/', $site->documentRoot->basePath);
        static::assertSame(PathType::File, $site->documentRoot->pathType);
        static::assertSame('CommonDoc', $site->projectName);
        static::assertSame('commondoc', $site->projectSlug);
        static::assertSame('1.0', $site->versionLabel);
    }

    #[Test]
    public function readsTheRootsAndTheOutputFromTheSameSection(): void
    {
        $site = self::site([
            'output' => 'site',
            'roots'  => [
                ['path' => 'docs/v1', 'mount' => '/commondoc/'],
                ['path' => '../other', 'mount' => '/other/'],
            ],
        ]);

        static::assertSame('site', $site->output);
        static::assertCount(2, $site->roots);
        static::assertSame('docs/v1', $site->roots[0]->path);
        static::assertSame('/commondoc/', $site->roots[0]->mount);
        static::assertSame('../other', $site->roots[1]->path);
        static::assertSame('/other/', $site->roots[1]->mount);
    }

    #[Test]
    public function refusesABasePathStatedInTheSiteSection(): void
    {
        // Two sources for one path space drift, and the loser is invisible: the
        // build writes one prefix while a template emits another. Naming where the
        // value moved is the only way an operator can find out.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/view_helper_config\.base_path/');

        self::site(section: ['basePath' => '/repo/']);
    }

    #[Test]
    public function refusesABasePathThatCannotPrefixAPath(): void
    {
        // The fallback above covers a section it cannot read. This is a section it
        // can read, holding a value that cannot work, and that belongs to the type
        // that owns the rule rather than to the reader.
        $this->expectException(InvalidArgumentException::class);

        self::site(viewHelperConfig: ['base_path' => 'repo']);
    }

    #[Test]
    public function refusesOverlappingMountsWithBothNamed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"a" and "b"/');

        self::site([
            'roots' => [
                ['path' => 'a', 'mount' => '/'],
                ['path' => 'b', 'mount' => '/guide/'],
            ],
        ]);
    }
}
