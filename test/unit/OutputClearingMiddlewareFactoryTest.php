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

use CommonDoc\Container\OutputClearingMiddlewareFactory;
use CommonDoc\Middleware\OutputClearingMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The clearing phase's construction seam.
 *
 * It takes no collaborators today. This exists so the pipeline resolves it by name, and so a
 * logger has one place to be injected when it gains one.
 */
#[CoversClass(OutputClearingMiddlewareFactory::class)]
#[CoversMethod(OutputClearingMiddlewareFactory::class, '__invoke')]
final class OutputClearingMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheMiddleware(): void
    {
        static::assertInstanceOf(
            OutputClearingMiddleware::class,
            new OutputClearingMiddlewareFactory()(
                $this->createStub(ContainerInterface::class),
                OutputClearingMiddleware::class,
            ),
        );
    }
}
