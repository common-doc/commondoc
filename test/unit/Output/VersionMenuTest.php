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

namespace CommonDocTest\Output;

use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Input\Component;
use CommonDoc\Input\Release;
use CommonDoc\Input\Root;
use CommonDoc\Input\Version;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\Output\VersionMenu;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Router\QueryParamRouter;

use function array_keys;

#[CoversClass(VersionMenu::class)]
#[CoversMethod(VersionMenu::class, '__construct')]
#[CoversMethod(VersionMenu::class, 'latest')]
#[CoversMethod(VersionMenu::class, 'links')]
final class VersionMenuTest extends TestCase
{
    #[Test]
    public function ignoresARootThatIsNotAReleasedVersion(): void
    {
        static::assertSame([], new VersionMenu([new Root(path: '/somewhere')])->latest());
    }

    #[Test]
    public function listsEveryVersionOfADocumentsComponentNewestFirst(): void
    {
        $menu = new VersionMenu([
            $this->root(
                component: 'bus',
                major    : 1,
            ),
            $this->root(
                component: 'bus',
                major    : 2,
            ),
            $this->root(
                component: 'log',
                major    : 1,
            ),
        ]);

        static::assertSame(
            [
                ['label' => 'v2 (latest)', 'url' => '/bus/v2/', 'current' => false],
                ['label' => 'v1', 'url' => '/bus/v1/', 'current' => true],
            ],
            $menu->links('bus/v1/guide/setup.md', $this->route()),
        );
    }

    #[Test]
    public function listsNothingForADocumentOutsideAVersionedComponent(): void
    {
        $menu = new VersionMenu([
            $this->root(
                component: 'bus',
                major    : 1,
            ),
            new Root(
                path : '/somewhere',
                mount: '/other/',
            ),
        ]);

        static::assertSame([], $menu->links('other/index.md', $this->route()));
        static::assertSame([], new VersionMenu([])->links('index.md', $this->route()));
    }

    #[Test]
    public function namesTheNewestVersionOfEachComponentWhateverTheConfiguredOrder(): void
    {
        $latest = new VersionMenu([
            $this->root(
                component: 'bus',
                major    : 1,
            ),
            $this->root(
                component: 'bus',
                major    : 2,
            ),
            $this->root(
                component: 'log',
                major    : 1,
            ),
        ])->latest();

        static::assertSame(['bus', 'log'], array_keys($latest));
        static::assertSame('/bus/v2/', $latest['bus']->mount);
    }

    private function root(string $component, int $major): Root
    {
        return new Root(
            path   : "/{$component}/{$major}",
            mount  : "/{$component}/v{$major}/",
            release: new Release(
                component: new Component($component),
                version  : Version::fromTag("v{$major}.0.0") ?? self::fail('The tag is not a version.'),
            ),
        );
    }

    private function route(): DocumentRoute
    {
        $root   = new DocumentRoot('/');
        $router = new QueryParamRouter();
        $router->addRoute(new Route(
            path      : '/{path}',
            middleware: new NotServedMiddleware(),
            name      : DocumentRoute::NAME,
        ));

        return new DocumentRoute($root, new UrlHelper($router));
    }
}
