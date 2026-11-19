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
 * Both halves matter. Reading it once is what stops the address space and the
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
     */
    private static function config(array $section): array
    {
        return [SiteConfig::class => $section];
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
     */
    private static function site(array $section = []): SiteConfig
    {
        return new SiteConfigFactory()(
            self::container(self::config([
                'basePath' => '/',
                'pathType' => PathType::Directory,
                ...$section,
            ])),
            SiteConfig::class,
        );
    }

    #[Test]
    public function defaultsToTheCustomDomainDeployment(): void
    {
        $site = self::site();

        static::assertSame('/', $site->root->basePath);
        static::assertSame(PathType::Directory, $site->root->pathType);
        static::assertSame('/guide/setup/', $site->root->published('guide/setup.md'));
    }

    #[Test]
    public function fallsBackWhenTheConfigServiceIsMissingEntirely(): void
    {
        // The container is asked for 'config' and may not have it; a stub that
        // returns null stands in for that case.
        $container = self::createStub(ContainerInterface::class);
        $container->method('get')->willReturn(null);

        $site = new SiteConfigFactory()($container, SiteConfig::class);

        static::assertSame('/', $site->root->basePath);
        static::assertSame(PathType::Directory, $site->root->pathType);
    }

    #[Test]
    public function fallsBackWhenTheConfigServiceIsNotAnArrayAtAll(): void
    {
        // A string is the case the guard exists for: reading an offset from one is
        // not a missing key, it is a TypeError.
        $container = self::createStub(ContainerInterface::class);
        $container->method('get')->willReturn('not config');

        $site = new SiteConfigFactory()($container, SiteConfig::class);

        static::assertSame('/', $site->root->basePath);
    }

    #[Test]
    public function fallsBackWhenTheSectionIsMalformed(): void
    {
        // A deployment with one wrong key should still build, so a section that
        // does not match the shape is replaced rather than refused.
        $site = new SiteConfigFactory()(
            self::container(self::config(['basePath' => 42])),
            SiteConfig::class,
        );

        static::assertSame('/', $site->root->basePath);
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
    public function readsTheAddressSpaceAndTheIdentityFromOneSection(): void
    {
        $site = self::site([
            'basePath'     => '/repo/',
            'pathType'     => PathType::File,
            'projectName'  => 'CommonDoc',
            'projectSlug'  => 'commondoc',
            'versionLabel' => '1.0',
        ]);

        static::assertSame('/repo/', $site->root->basePath);
        static::assertSame(PathType::File, $site->root->pathType);
        static::assertSame('CommonDoc', $site->projectName);
        static::assertSame('commondoc', $site->projectSlug);
        static::assertSame('1.0', $site->versionLabel);
    }

    #[Test]
    public function refusesABasePathThatCannotPrefixAnAddress(): void
    {
        // The fallback above covers a section it cannot read. This is a section it
        // can read, holding a value that cannot work, and that belongs to the type
        // that owns the rule rather than to the reader.
        $this->expectException(InvalidArgumentException::class);

        self::site(['basePath' => 'repo']);
    }
}
