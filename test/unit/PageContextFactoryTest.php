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

use CommonDoc\Container\PageContextFactory;
use CommonDoc\View\PageContext;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageContextFactory::class)]
#[CoversMethod(PageContextFactory::class, '__invoke')]
final class PageContextFactoryTest extends TestCase
{
    #[Test]
    public function buildsAPageContext(): void
    {
        static::assertInstanceOf(PageContext::class, $this->container()->get(PageContext::class));
    }

    #[Test]
    public function sharesOneContextPerContainer(): void
    {
        $container = $this->container();

        static::assertSame($container->get(PageContext::class), $container->get(PageContext::class));
    }

    private function container(): ServiceManager
    {
        return new ServiceManager([
            'factories' => [PageContext::class => PageContextFactory::class],
        ]);
    }
}
