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

use CommonDoc\Container\SourceLoadingMiddlewareFactory;
use CommonDoc\Middleware\SourceLoadingMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(SourceLoadingMiddlewareFactory::class)]
#[CoversMethod(SourceLoadingMiddlewareFactory::class, '__invoke')]
final class SourceLoadingMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheMiddleware(): void
    {
        $subject = new SourceLoadingMiddlewareFactory()(
            $this->createStub(ContainerInterface::class),
            SourceLoadingMiddleware::class,
        );

        static::assertInstanceOf(SourceLoadingMiddleware::class, $subject);
    }
}
