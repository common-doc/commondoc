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
use Laminas\ServiceManager\ServiceManager;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The shared converter: the environment the container serves, and nothing added to it.
 *
 * It carries no link rewriter, so `[intro](intro.md)` keeps the target exactly as written.
 * A build never uses this converter; it is handed the callable `ConverterFactoryFactory`
 * returns, which is why the options a caller might pass here are not read.
 */
#[CoversClass(MarkdownConverterFactory::class)]
#[CoversMethod(MarkdownConverterFactory::class, '__invoke')]
final class MarkdownConverterFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheConverterAroundTheEnvironmentFromTheContainer(): void
    {
        $converter = new MarkdownConverterFactory()($this->container(), MarkdownConverter::class);

        static::assertInstanceOf(MarkdownConverter::class, $converter);
    }

    #[Test]
    public function ignoresOptionsEntirely(): void
    {
        // The options argument is open to any caller. It is not read, so a caller that
        // still passes the lists a build used to pass gets the plain converter and not a
        // silently rewritten one.
        $converter = new MarkdownConverterFactory()(
            $this->container(),
            MarkdownConverter::class,
            ['documents' => ['guide/intro.md'], 'assets' => []],
        );

        $html = (string) $converter->convert('[intro](intro.md)');

        static::assertStringNotContainsString('/guide/intro/', $html);
        static::assertStringContainsString('intro.md', $html);
    }

    private function container(): ServiceManager
    {
        return new ServiceManager([
            'factories' => [
                EnvironmentInterface::class => static function (): Environment {
                    $environment = new Environment();
                    $environment->addExtension(new CommonMarkCoreExtension());

                    return $environment;
                },
            ],
        ]);
    }
}
