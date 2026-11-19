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
use CommonDoc\SiteConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type\Exception\CoercionException;
use Psr\Container\ContainerInterface;

/**
 * The factory only serves the site's translation under its own name.
 *
 * How the base path and path type are read is `SiteConfigFactoryTest`'s concern,
 * because one section now feeds both. What is left here is the delegation, and the
 * refusal when the site is not what it claims to be.
 */
#[CoversClass(DocumentRootFactory::class)]
#[CoversMethod(DocumentRootFactory::class, '__invoke')]
final class DocumentRootFactoryTest extends TestCase
{
    /**
     * @param mixed $siteConfig Whatever the container should serve for the site
     */
    private static function container(mixed $siteConfig): ContainerInterface
    {
        $container = self::createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => SiteConfig::class === $id ? $siteConfig : null,
            );

        return $container;
    }

    #[Test]
    public function refusesASiteThatIsNotASiteConfig(): void
    {
        // A misregistered service is a wiring failure, so it is refused here with
        // a typed message rather than surfacing as a fatal further in.
        $this->expectException(CoercionException::class);

        new DocumentRootFactory()(self::container(null), DocumentRoot::class);
    }

    #[Test]
    public function servesTheRootTheSiteCarries(): void
    {
        $root = new DocumentRoot(
            basePath: '/repo/',
            pathType: PathType::File,
        );

        $subject = new DocumentRootFactory()(
            self::container(new SiteConfig(root: $root)),
            DocumentRoot::class,
        );

        // The same instance, not an equal one: a second translation would be a
        // second rule, and a link and the file serving it could disagree.
        static::assertSame($root, $subject);
    }
}
