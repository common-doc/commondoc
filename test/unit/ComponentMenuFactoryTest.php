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

use CommonDoc\Container\ComponentMenuFactory;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\RouteProvider;
use CommonDoc\View\Helper\ComponentMenu;
use Laminas\Escaper\Escaper;
use Laminas\Escaper\EscaperInterface;
use Laminas\View\HelperPluginManager;
use Mezzio\Router\RouteCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Webware\Navigation\View\Helper\Navigation;
use Webware\Router\QueryParamRouter;

/**
 * The components menu's collaborators, resolved the way a build resolves them.
 *
 * The navigation helper is fetched from the plugin manager, because that is where
 * `webware-navigation` registers it: it has no service of its own.
 */
#[CoversClass(ComponentMenuFactory::class)]
#[CoversMethod(ComponentMenuFactory::class, '__invoke')]
final class ComponentMenuFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheHelperFromThePluginManager(): void
    {
        $subject = new ComponentMenuFactory()($this->container(), ComponentMenu::class);

        static::assertInstanceOf(ComponentMenu::class, $subject);
    }

    #[Test]
    public function rendersTheRoutesTheCollectorHolds(): void
    {
        $subject = new ComponentMenuFactory()($this->container(), ComponentMenu::class);

        static::assertStringContainsString(
            'href="&#x2F;comp&#x2F;">Comp</a>',
            $subject->__invoke(),
        );
    }

    private function container(): ContainerInterface
    {
        $collector = new RouteCollector(new QueryParamRouter());

        $collector
            ->route(
                path      : '/comp/',
                middleware: new NotServedMiddleware(),
            )
            ->setOptions([
                'navigation' => RouteProvider::COMPONENTS,
                'label'      => 'Comp',
                'order'      => 0,
            ]);

        $helpers = new HelperPluginManager($this->createStub(ContainerInterface::class));
        $helpers->setService(Navigation::class, new Navigation(routeCollector: $collector));

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    HelperPluginManager::class => $helpers,
                    Escaper::class             => new Escaper(),
                    EscaperInterface::class    => new Escaper(),
                    default                    => self::fail("Unexpected service: {$id}"),
                },
            );

        return $container;
    }
}
