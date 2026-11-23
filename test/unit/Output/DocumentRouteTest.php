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
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\Output\PathType;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\Exception\RuntimeException as RouterException;
use Mezzio\Router\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Router\QueryParamRouter;

#[CoversClass(DocumentRoute::class)]
#[CoversMethod(DocumentRoute::class, '__construct')]
#[CoversMethod(DocumentRoute::class, 'file')]
#[CoversMethod(DocumentRoute::class, 'isIndex')]
#[CoversMethod(DocumentRoute::class, 'pattern')]
#[CoversMethod(DocumentRoute::class, 'url')]
final class DocumentRouteTest extends TestCase
{
    #[Test]
    public function composesThePatternFromTheBasePath(): void
    {
        static::assertSame('/repo/{path}', $this->subject('/repo')->pattern());
    }

    #[Test]
    public function generatesFilePathsWhenTheRootPublishesFiles(): void
    {
        static::assertSame('/guide/setup.html', $this->subject('/', PathType::File)->url('guide/setup.md'));
    }

    #[Test]
    public function generatesTheUrlFromThePatternAndThePath(): void
    {
        $subject = $this->subject('/repo');

        static::assertSame('/repo/guide/setup/', $subject->url('guide/setup.md'));
        static::assertSame('/repo/', $subject->url('index.md'));
    }

    #[Test]
    public function leavesThePlaceholderWhenThePatternRouteIsMissingItsValue(): void
    {
        // Pins the router's behaviour: a parameter it was not given is left in the
        // address, which is why the value always comes from `DocumentRoot::pathOf()`.
        $router = new QueryParamRouter();
        $router->addRoute(new Route(
            path      : '/{path}',
            middleware: new NotServedMiddleware(),
            name      : DocumentRoute::NAME,
        ));

        static::assertSame('/{path}', new UrlHelper($router)->generate(routeName: DocumentRoute::NAME));
    }

    #[Test]
    public function refusesToGenerateBeforeThePatternRouteIsRegistered(): void
    {
        $this->expectException(RouterException::class);

        new DocumentRoute(new DocumentRoot('/'), new UrlHelper(new QueryParamRouter()))->url('index.md');
    }

    #[Test]
    public function reportsWhetherADocumentIsItsDirectorysIndex(): void
    {
        $subject = $this->subject('/');

        static::assertTrue($subject->isIndex('guide/README.md'));
        static::assertFalse($subject->isIndex('guide/setup.md'));
    }

    #[Test]
    public function writesADocumentToTheFileTheRootNames(): void
    {
        static::assertSame('guide/setup/index.html', $this->subject('/')->file('guide/setup.md'));
    }

    private function subject(string $basePath, PathType $type = PathType::Directory): DocumentRoute
    {
        $root   = new DocumentRoot($basePath, $type);
        $router = new QueryParamRouter();
        $router->addRoute(new Route(
            path      : new DocumentRoute($root, new UrlHelper($router))->pattern(),
            middleware: new NotServedMiddleware(),
            name      : DocumentRoute::NAME,
        ));

        return new DocumentRoute($root, new UrlHelper($router));
    }
}
