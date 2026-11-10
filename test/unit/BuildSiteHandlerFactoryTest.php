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

use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\Container\BuildSiteHandlerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(BuildSiteHandlerFactory::class)]
#[CoversMethod(BuildSiteHandlerFactory::class, '__invoke')]
final class BuildSiteHandlerFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheHandler(): void
    {
        $subject = new BuildSiteHandlerFactory()($this->createStub(ContainerInterface::class), BuildSiteHandler::class);

        static::assertInstanceOf(BuildSiteHandler::class, $subject);
    }
}
