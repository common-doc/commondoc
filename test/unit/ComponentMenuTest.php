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
use CommonDoc\Output\RouteProvider;
use CommonDoc\View\Dropdown;
use CommonDoc\View\Helper\ComponentMenu;
use Laminas\Escaper\Escaper;
use Mezzio\Router\RouteCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Navigation\View\Helper\Navigation;
use Webware\Router\QueryParamRouter;

/**
 * The components menu the layout renders in its navbar.
 *
 * The links are routes carrying the options `webware-navigation` reads, so the fixture
 * registers routes rather than building items by hand: what the menu shows has to come
 * from the same table a real build writes.
 */
#[CoversClass(ComponentMenu::class)]
#[CoversMethod(ComponentMenu::class, '__construct')]
#[CoversMethod(ComponentMenu::class, '__invoke')]
final class ComponentMenuTest extends TestCase
{
    #[Test]
    public function escapesTheLabelAndTheAddress(): void
    {
        $menu = $this->menu([['path' => '/a"b/', 'label' => 'A < B']]);

        // Escaped by the escaper, in attribute context, as the rest of the layout is.
        static::assertStringContainsString('href="&#x2F;a&quot;b&#x2F;"', $menu);
        static::assertStringContainsString('>A &lt; B</a>', $menu);
        static::assertStringNotContainsString('href="/a"b/"', $menu);
    }

    #[Test]
    public function namesTheButtonWithTheDefaultLabel(): void
    {
        static::assertStringContainsString(
            'data-dropdown-toggle="components">Components</button>',
            $this->menu([['path' => '/comp/', 'label' => 'Comp']]),
        );
    }

    #[Test]
    public function rendersEachComponentAsADropdownItem(): void
    {
        $menu = $this->menu([['path' => '/comp/', 'label' => 'Comp']]);

        static::assertStringContainsString('data-dropdown="components"', $menu);
        static::assertStringContainsString('data-bs-toggle="dropdown"', $menu);
        static::assertStringContainsString('href="&#x2F;comp&#x2F;">Comp</a>', $menu);
    }

    #[Test]
    public function rendersNothingWhenTheSiteHasNoReleasedComponent(): void
    {
        static::assertSame('', $this->menu());
    }

    #[Test]
    public function takesTheLabelItIsGiven(): void
    {
        static::assertStringContainsString(
            'data-dropdown-toggle="components">Artifacts</button>',
            $this->menu([['path' => '/comp/', 'label' => 'Comp']], label: 'Artifacts'),
        );
    }

    /**
     * @param list<array{path: non-empty-string, label: non-empty-string}> $components
     */
    private function menu(array $components = [], string $label = 'Components'): string
    {
        $collector = new RouteCollector(new QueryParamRouter());
        $order     = 0;

        foreach ($components as $component) {
            $collector
                ->route(
                    path      : $component['path'],
                    middleware: new NotServedMiddleware(),
                )
                ->setOptions([
                    'navigation' => RouteProvider::COMPONENTS,
                    'label'      => $component['label'],
                    'order'      => $order,
                ]);

            $order++;
        }

        return new ComponentMenu(
            navigation: new Navigation(routeCollector: $collector),
            dropdown  : new Dropdown(new Escaper()),
        )($label);
    }
}
