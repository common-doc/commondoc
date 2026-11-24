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

use CommonDoc\Container\PageMenuFactory;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\NavigationScope;
use CommonDoc\View\Helper\PageMenu;
use CommonDoc\View\PageContext;
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
 * The pages list's collaborators, resolved the way a build resolves them.
 *
 * The navigation helper is fetched from the plugin manager, because that is where
 * `webware-navigation` registers it: it has no service of its own. The page context is
 * fetched from the root container, which is where the build registers it.
 */
#[CoversClass(PageMenuFactory::class)]
#[CoversMethod(PageMenuFactory::class, '__invoke')]
final class PageMenuFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheHelperFromThePluginManagerAndTheContext(): void
    {
        $subject = new PageMenuFactory()($this->container(), PageMenu::class);

        static::assertInstanceOf(PageMenu::class, $subject);
    }

    #[Test]
    public function rendersTheRoutesTheCollectorHolds(): void
    {
        $subject = new PageMenuFactory()($this->container(), PageMenu::class);

        static::assertStringContainsString(
            'href="&#x2F;one&#x2F;">One</a>',
            $subject->__invoke(),
        );
    }

    private function container(): ContainerInterface
    {
        $collector = new RouteCollector(new QueryParamRouter());

        $collector
            ->route(
                path      : '/one/',
                middleware: new NotServedMiddleware(),
            )
            ->setOptions([
                'navigation' => NavigationScope::BASE,
                'label'      => 'One',
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
                    PageContext::class         => new PageContext(),
                    Escaper::class             => new Escaper(),
                    EscaperInterface::class    => new Escaper(),
                    default                    => self::fail("Unexpected service: {$id}"),
                },
            );

        return $container;
    }
}
