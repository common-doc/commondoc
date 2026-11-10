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

use CommonDoc\Container\LayoutWrappingMiddlewareFactory;
use CommonDoc\Middleware\LayoutWrappingMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(LayoutWrappingMiddlewareFactory::class)]
#[CoversMethod(LayoutWrappingMiddlewareFactory::class, '__invoke')]
final class LayoutWrappingMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheMiddleware(): void
    {
        $subject = new LayoutWrappingMiddlewareFactory()(
            $this->createStub(ContainerInterface::class),
            LayoutWrappingMiddleware::class,
        );

        static::assertInstanceOf(LayoutWrappingMiddleware::class, $subject);
    }
}
