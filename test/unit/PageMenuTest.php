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

use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\NavigationScope;
use CommonDoc\View\Helper\PageMenu;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageList;
use Laminas\Escaper\Escaper;
use Mezzio\Router\RouteCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Navigation\View\Helper\Navigation;
use Webware\Router\QueryParamRouter;

/**
 * The pages list the layout renders in its sidebar.
 *
 * The links are routes carrying the options `webware-navigation` reads, so the fixture
 * registers routes rather than building items by hand: what the list shows has to come
 * from the same table a real build writes. Which menu is listed comes from the page the
 * build is converting, which is the only thing that tells a layout where it is rendering.
 */
#[CoversClass(PageMenu::class)]
#[CoversMethod(PageMenu::class, '__construct')]
#[CoversMethod(PageMenu::class, '__invoke')]
final class PageMenuTest extends TestCase
{
    #[Test]
    public function escapesTheLabelAndTheAddress(): void
    {
        $html = $this->menu([['path' => '/a"b/', 'label' => 'A < B', 'navigation' => NavigationScope::BASE]]);

        static::assertStringContainsString('href="&#x2F;a&quot;b&#x2F;"', $html);
        static::assertStringContainsString('>A &lt; B</a>', $html);
    }

    #[Test]
    public function listsTheBaseMenuWhenNoPageIsBeingConverted(): void
    {
        $html = $this->menu([
            ['path' => '/one/', 'label' => 'One', 'navigation' => NavigationScope::BASE],
            ['path' => '/other/', 'label' => 'Other', 'navigation' => 'commondoc:/other/'],
        ]);

        static::assertStringContainsString('>One</a>', $html);
        static::assertStringNotContainsString('>Other</a>', $html);
    }

    #[Test]
    public function listsTheMenuThePageNames(): void
    {
        $html = $this->menu(
            [
                ['path' => '/one/', 'label' => 'One', 'navigation' => NavigationScope::BASE],
                ['path' => '/other/', 'label' => 'Other', 'navigation' => 'commondoc:/other/'],
            ],
            navigation: 'commondoc:/other/',
        );

        static::assertStringContainsString('>Other</a>', $html);
        static::assertStringNotContainsString('>One</a>', $html);
    }

    #[Test]
    public function rendersNothingWhenTheMenuHasNoPages(): void
    {
        static::assertSame('', $this->menu());
    }

    #[Test]
    public function takesTheHeadingItIsGiven(): void
    {
        static::assertStringContainsString(
            '>Contents</h2>',
            $this->menu(
                [['path' => '/one/', 'label' => 'One', 'navigation' => NavigationScope::BASE]],
                label: 'Contents',
            ),
        );
    }

    /**
     * @param list<array{path: non-empty-string, label: non-empty-string, navigation: non-empty-string}> $pages
     */
    private function menu(
        array $pages = [],
        string $label = 'Pages',
        string $navigation = NavigationScope::BASE,
    ): string {
        $collector = new RouteCollector(new QueryParamRouter());
        $context   = new PageContext();
        $order     = 0;

        foreach ($pages as $page) {
            $collector
                ->route(
                    path      : $page['path'],
                    middleware: new NotServedMiddleware(),
                )
                ->setOptions([
                    'navigation' => $page['navigation'],
                    'label'      => $page['label'],
                    'order'      => $order,
                ]);

            $order++;
        }

        if (NavigationScope::BASE !== $navigation) {
            $context->set(new Page(
                label       : 'Other',
                projectName : 'Project',
                projectSlug : 'project',
                sourcePath  : 'other/index.md',
                url         : '/other/',
                versionLabel: 'v1',
                routeName   : 'commondoc.other.index',
                navigation  : $navigation,
            ));
        }

        return new PageMenu(
            navigation: new Navigation(routeCollector: $collector),
            context   : $context,
            list      : new PageList(escaper: new Escaper()),
        )($label);
    }
}
