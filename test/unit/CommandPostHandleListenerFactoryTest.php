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

use CommonDoc\Container\CommandPostHandleListenerFactory;
use CommonDoc\Listener\CommandPostHandleListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(CommandPostHandleListenerFactory::class)]
#[CoversMethod(CommandPostHandleListenerFactory::class, '__invoke')]
final class CommandPostHandleListenerFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheListener(): void
    {
        $subject = new CommandPostHandleListenerFactory()(
            $this->createStub(ContainerInterface::class),
            CommandPostHandleListener::class,
        );

        static::assertInstanceOf(CommandPostHandleListener::class, $subject);
    }
}
