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

use CommonDoc\Container\VersionSwitchFactory;
use CommonDoc\View\Helper\VersionSwitch;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use Laminas\Escaper\Escaper;
use Laminas\Escaper\EscaperInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The version menu's collaborators, resolved from a container.
 */
#[CoversClass(VersionSwitchFactory::class)]
#[CoversMethod(VersionSwitchFactory::class, '__invoke')]
final class VersionSwitchFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheHelper(): void
    {
        static::assertInstanceOf(
            VersionSwitch::class,
            new VersionSwitchFactory()($this->container(new PageContext()), VersionSwitch::class),
        );
    }

    #[Test]
    public function readsTheVersionFromTheContextItIsGiven(): void
    {
        $context = new PageContext();
        $context->set(new Page(
            label       : 'Guide',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'bus/v1/index.md',
            url         : '/bus/v1/',
            versionLabel: '1.0',
            routeName   : '/bus/v1/',
            versions    : [['label' => 'v1', 'url' => '/bus/v1/', 'current' => true]],
        ));

        $subject = new VersionSwitchFactory()($this->container($context), VersionSwitch::class);

        static::assertStringContainsString('disabled>v1</button>', $subject->__invoke());
    }

    private function container(PageContext $context): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    PageContext::class      => $context,
                    EscaperInterface::class => new Escaper(),
                    default                 => self::fail("Unexpected service: {$id}"),
                },
            );

        return $container;
    }
}
