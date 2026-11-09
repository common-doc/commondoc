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

use CommonDoc\Container\MarkdownConverterFactory;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(MarkdownConverterFactory::class)]
#[CoversMethod(MarkdownConverterFactory::class, '__invoke')]
final class MarkdownConverterFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheConverterAroundTheEnvironmentFromTheContainer(): void
    {
        $environment = new Environment();

        $converter = new MarkdownConverterFactory()(
            $this->containerWithEnvironment($environment),
            MarkdownConverter::class,
        );

        static::assertSame($environment, $converter->getEnvironment());
    }

    private function containerWithEnvironment(EnvironmentInterface $environment): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => EnvironmentInterface::class === $id ? $environment : null,
            );

        return $container;
    }
}
