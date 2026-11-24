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

use CommonDoc\Container\FaviconFactory;
use CommonDoc\View\Helper\Favicon;
use CommonDoc\View\Helper\ThemeAsset;
use Laminas\Escaper\Escaper;
use Laminas\View\Helper\Asset;
use Laminas\View\Helper\BasePath;
use Laminas\View\Helper\Doctype;
use Laminas\View\Helper\HeadLink;
use Laminas\View\HelperPluginManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The favicon's collaborators, resolved the way a build resolves them.
 *
 * `headLink` is the queue the link lands in, so the assertion is on what it holds after the
 * helper has run rather than on a string the helper returns.
 */
#[CoversClass(FaviconFactory::class)]
#[CoversMethod(FaviconFactory::class, '__invoke')]
final class FaviconFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheHelperFromThePluginManager(): void
    {
        static::assertInstanceOf(
            Favicon::class,
            new FaviconFactory()($this->container(), Favicon::class),
        );
    }

    #[Test]
    public function queuesTheIconTheThemeDeclares(): void
    {
        $headLink = new HeadLink(new Escaper(), new Doctype());
        $subject  = new FaviconFactory()($this->container($headLink), Favicon::class);

        $subject();

        static::assertStringContainsString(
            'href="&#x2F;theme&#x2F;webware&#x2F;favicon.svg"',
            (string) $headLink,
        );
    }

    private function container(?HeadLink $headLink = null): ContainerInterface
    {
        $headLink ??= new HeadLink(new Escaper(), new Doctype());

        $helpers = new HelperPluginManager($this->createStub(ContainerInterface::class));
        $helpers->setService(
            ThemeAsset::class,
            new ThemeAsset(
                asset   : new Asset(['favicon' => '/theme/webware/favicon.svg']),
                basePath: new BasePath('/'),
            ),
        );
        $helpers->setService(HeadLink::class, $headLink);

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    HelperPluginManager::class => $helpers,
                    default                    => self::fail("Unexpected service: {$id}"),
                },
            );

        return $container;
    }
}
