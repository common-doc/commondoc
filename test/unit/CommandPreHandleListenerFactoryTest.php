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

use CommonDoc\Container\CommandPreHandleListenerFactory;
use CommonDoc\Listener\CommandPreHandleListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(CommandPreHandleListenerFactory::class)]
#[CoversMethod(CommandPreHandleListenerFactory::class, '__invoke')]
final class CommandPreHandleListenerFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheListener(): void
    {
        $subject = new CommandPreHandleListenerFactory()(
            $this->createStub(ContainerInterface::class),
            CommandPreHandleListener::class,
        );

        static::assertInstanceOf(CommandPreHandleListener::class, $subject);
    }
}
