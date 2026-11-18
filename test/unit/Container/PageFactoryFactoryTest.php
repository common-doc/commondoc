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

use CommonDoc\Container\PageFactoryFactory;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\Page;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type\Exception\CoercionException;
use Psr\Container\ContainerInterface;

#[CoversClass(PageFactoryFactory::class)]
#[CoversMethod(PageFactoryFactory::class, '__invoke')]
final class PageFactoryFactoryTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $config
     */
    private static function container(array $config, DocumentRoot $root): ContainerInterface
    {
        $container = self::createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    'config'            => $config,
                    DocumentRoot::class => $root,
                    default             => null,
                },
            );

        return $container;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function factory(array $config = [], ?DocumentRoot $root = null): callable
    {
        return new PageFactoryFactory()(
            self::container($config, $root ?? new DocumentRoot('/repo/')),
            Page::class,
        );
    }

    #[Test]
    public function acceptsChildrenWhenTheyArePages(): void
    {
        $factory = self::factory();

        $child = $factory(
            localPath: 'guide/setup.md',
            label    : 'Setup',
        );
        $parent = $factory(
            localPath: 'guide/index.md',
            label    : 'Guide',
            children : [$child],
        );

        static::assertSame([$child], $parent->getChildren());
        static::assertTrue($parent->hasChildren());
    }

    #[Test]
    public function capturesTheDeploymentIdentityOnceForEveryPage(): void
    {
        $factory = self::factory([
            Page::class => [
                'projectName'  => 'CommonDoc',
                'projectSlug'  => 'commondoc',
                'versionLabel' => '1.0',
            ],
        ]);

        $first = $factory(
            localPath: 'index.md',
            label    : 'Home',
        );
        $second = $factory(
            localPath: 'guide/setup.md',
            label    : 'Setup',
        );

        static::assertSame('CommonDoc', $first->projectName);
        static::assertSame('commondoc', $first->projectSlug);
        static::assertSame('1.0', $first->versionLabel);

        // The identity is shared; only what differs per document is not.
        static::assertSame($first->projectName, $second->projectName);
        static::assertSame($first->projectSlug, $second->projectSlug);
        static::assertSame($first->versionLabel, $second->versionLabel);
        static::assertNotSame($first->url, $second->url);
    }

    #[Test]
    public function derivesTheAddressRatherThanAcceptingOne(): void
    {
        $page = self::factory()(
            localPath: 'guide/setup.md',
            label    : 'Setup',
        );

        static::assertSame('guide/setup.md', $page->sourcePath);
        static::assertSame('/repo/guide/setup/', $page->url);
    }

    #[Test]
    public function leavesTheIdentityBlankWhenNoSectionIsConfigured(): void
    {
        $page = self::factory()(
            localPath: 'index.md',
            label    : 'Home',
        );

        static::assertSame('', $page->projectName);
        static::assertSame('', $page->projectSlug);
        static::assertSame('', $page->versionLabel);
    }

    #[Test]
    public function leavesTheIdentityBlankWhenTheConfigServiceIsMissing(): void
    {
        // A container without 'config' still yields a usable factory: a page with
        // no project name is cosmetic, a build that cannot start is not.
        $container = self::createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => DocumentRoot::class === $id ? new DocumentRoot('/') : null,
            );

        $factory = new PageFactoryFactory()($container, Page::class);

        $page = $factory(
            localPath: 'index.md',
            label    : 'Home',
        );

        static::assertSame('', $page->projectName);
        static::assertSame('/', $page->url);
    }

    #[Test]
    public function refusesAChildThatIsNotAPage(): void
    {
        $this->expectException(CoercionException::class);

        self::factory()(
            localPath: 'guide/index.md',
            label    : 'Guide',
            children : ['not a page'],
        );
    }

    #[Test]
    public function refusesALocalPathThatIsNotADocument(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::factory()(
            localPath: 'guide/setup.html',
            label    : 'Setup',
        );
    }
}
