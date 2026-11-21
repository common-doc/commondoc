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

namespace CommonDocTest\Container;

use Closure;
use CommonDoc\Container\PageFactoryFactory;
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Source;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\SiteConfig;
use CommonDoc\View\Page;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\Exception\RuntimeException as RouterException;
use Mezzio\Router\Route;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Router\QueryParamRouter;

/**
 * The function a build calls to get the page for one document.
 *
 * What the container serves under this factory's name is a callable, so every test
 * resolves it once and calls it the way the handler does. The caller supplies what varies
 * per document; everything a deployment shares comes from `SiteConfig`, and the URL is
 * generated from the routing table, because a page that carried its own could disagree with
 * the file the write pass produces for the same document.
 */
#[CoversClass(PageFactoryFactory::class)]
#[CoversMethod(PageFactoryFactory::class, '__invoke')]
final class PageFactoryFactoryTest extends TestCase
{
    /**
     * The function the container would serve for this factory's name.
     *
     * @param array{projectName?: string, projectSlug?: string, versionLabel?: string} $identity
     *
     * @return Closure(non-empty-string $localPath, string $label, ?Source $source, ?string $repositoryPath): Page
     */
    private static function pages(array $identity = [], ?DocumentRoot $root = null): Closure
    {
        $root ??= new DocumentRoot('/repo/');

        $siteConfig = new SiteConfig(
            documentRoot: $root,
            projectName : $identity['projectName'] ?? '',
            projectSlug : $identity['projectSlug'] ?? '',
            versionLabel: $identity['versionLabel'] ?? '',
        );

        $container = self::createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    SiteConfig::class => $siteConfig,
                    UrlHelper::class  => self::url($root),
                    default           => null,
                },
            );

        return new PageFactoryFactory()($container, PageFactoryFactory::class);
    }

    /**
     * The real helper over the real router, for the same reason the link tests use it:
     * the URL a page carries is meant to come from the router, so a stub would assert
     * the expectation instead of the behaviour.
     *
     * Every document these tests build a page for is registered the way a build
     * registers one: at its published path, with no name.
     */
    private static function url(DocumentRoot $root): UrlHelper
    {
        $middleware = new class implements MiddlewareInterface {
            #[Override]
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $handler->handle($request);
            }
        };

        $router = new QueryParamRouter();

        foreach (['guide/setup.md', 'guide/index.md', 'index.md'] as $document) {
            $router->addRoute(new Route($root->published($document), $middleware));
        }

        return new UrlHelper($router);
    }

    #[Test]
    public function capturesTheDeploymentIdentityForEveryPage(): void
    {
        $pages = self::pages([
            'projectName'  => 'CommonDoc',
            'projectSlug'  => 'commondoc',
            'versionLabel' => '1.0',
        ]);

        $first = $pages(
            localPath: 'index.md',
            label    : 'Home',
        );
        $second = $pages(
            localPath: 'guide/setup.md',
            label    : 'Setup',
        );

        static::assertSame('CommonDoc', $first->projectName);
        static::assertSame('commondoc', $first->projectSlug);
        static::assertSame('1.0', $first->versionLabel);

        // The identity is shared; only what differs per document is not.
        static::assertSame($first->projectName, $second->projectName);
        static::assertSame($first->projectSlug, $second->projectSlug);
        static::assertSame($first->versionLabel, $second->versionLabel);
        static::assertNotSame($first->url, $second->url);
    }

    #[Test]
    public function carriesTheSourceAndTheRepositoryPathItWasGiven(): void
    {
        $source = new Source('https://github.com/example/repo', 'abc123', 'docs/2.0');

        $page = self::pages()(
            localPath     : 'guide/setup.md',
            label         : 'Setup',
            source        : $source,
            repositoryPath: 'docs/2.0/guide/setup.md',
        );

        static::assertSame($source, $page->source);
        static::assertSame('docs/2.0/guide/setup.md', $page->repositoryPath);
    }

    #[Test]
    public function derivesThePublishedPathRatherThanAcceptingOne(): void
    {
        $page = self::pages()(
            localPath: 'guide/setup.md',
            label    : 'Setup',
        );

        static::assertSame('guide/setup.md', $page->sourcePath);
        static::assertSame('/repo/guide/setup/', $page->url);
        static::assertSame('/repo/guide/setup/', $page->routeName);
    }

    #[Test]
    public function leavesTheIdentityBlankWhenNoSectionIsConfigured(): void
    {
        // A deployment that states no identity still yields a usable page: a page
        // with no project name is cosmetic, a build that cannot start is not.
        $page = self::pages(root: new DocumentRoot('/'))(
            localPath: 'index.md',
            label    : 'Home',
        );

        static::assertSame('', $page->projectName);
        static::assertSame('', $page->projectSlug);
        static::assertSame('', $page->versionLabel);
        static::assertSame('/', $page->url);
    }

    #[Test]
    public function refusesADocumentTheBuildDidNotRegister(): void
    {
        // The URL comes from the routing table, so a document with no route has no URL.
        // That is a defect in the build's order, not in the document, and it must not be
        // answered with an address composed here.
        $this->expectException(RouterException::class);

        self::pages()(
            localPath: 'unregistered.md',
            label    : 'Unregistered',
        );
    }

    #[Test]
    public function refusesALocalPathThatIsNotADocument(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::pages()(
            localPath: 'guide/setup.html',
            label    : 'Setup',
        );
    }
}
