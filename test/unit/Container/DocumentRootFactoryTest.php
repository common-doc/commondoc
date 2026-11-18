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

use CommonDoc\Container\DocumentRootFactory;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\PathType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(DocumentRootFactory::class)]
#[CoversMethod(DocumentRootFactory::class, '__invoke')]
final class DocumentRootFactoryTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $config
     */
    private static function container(array $config): ContainerInterface
    {
        $container = self::createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => 'config' === $id ? $config : null,
            );

        return $container;
    }

    #[Test]
    public function buildsTheRootFromConfiguration(): void
    {
        $root = new DocumentRootFactory()(self::container([
            DocumentRoot::class => [
                'basePath' => '/repo/',
                'pathType' => PathType::File,
            ],
        ]), DocumentRoot::class);

        static::assertSame('/repo/', $root->basePath);
        static::assertSame('/repo/guide/setup.html', $root->published('guide/setup.md'));
    }

    #[Test]
    public function defaultsToTheDirectoryPathType(): void
    {
        $root = new DocumentRootFactory()(self::container([]), DocumentRoot::class);

        static::assertSame(PathType::Directory, $root->pathType);
        static::assertSame('guide/setup/index.html', $root->file('guide/setup.md'));
    }

    #[Test]
    public function fallsBackToTheCustomDomainDefaults(): void
    {
        $root = new DocumentRootFactory()(self::container([]), DocumentRoot::class);

        static::assertSame('/', $root->basePath);
        static::assertSame('/guide/setup/', $root->published('guide/setup.md'));
    }

    #[Test]
    public function fallsBackWhenTheConfigServiceIsMissingEntirely(): void
    {
        // The container is asked for 'config' and may not have it; a stub that
        // returns null stands in for that case.
        $container = self::createStub(ContainerInterface::class);
        $container->method('get')->willReturn(null);

        $root = new DocumentRootFactory()($container, DocumentRoot::class);

        static::assertSame('/', $root->basePath);
        static::assertSame(PathType::Directory, $root->pathType);
    }

    #[Test]
    public function fallsBackWhenTheSectionIsMalformed(): void
    {
        // A deployment with one wrong key should still build, so a section that
        // does not match the shape is replaced rather than refused.
        $root = new DocumentRootFactory()(self::container([
            DocumentRoot::class => ['basePath' => 42],
        ]), DocumentRoot::class);

        static::assertSame('/', $root->basePath);
    }
}
