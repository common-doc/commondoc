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
use CommonDoc\Input\Component;
use CommonDoc\Input\Release;
use CommonDoc\Input\Root;
use CommonDoc\Input\Source;
use CommonDoc\Input\Version;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\SiteConfig;
use CommonDoc\View\Page;
use Mezzio\Helper\UrlHelper;
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
     * @param array{siteName?: string, versionLabel?: string} $identity
     *
     * @return Closure(non-empty-string $localPath, string $label, ?Source $source, ?string $repositoryPath): Page
     */
    private static function pages(array $identity = [], ?DocumentRoot $root = null, array $roots = []): Closure
    {
        $root ??= new DocumentRoot('/repo/');

        $siteConfig = new SiteConfig(
            documentRoot: $root,
            roots       : $roots,
            siteName    : $identity['siteName'] ?? '',
            versionLabel: $identity['versionLabel'] ?? '',
        );

        $container = self::createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    SiteConfig::class    => $siteConfig,
                    DocumentRoute::class => self::url($root),
                    default              => null,
                },
            );

        return new PageFactoryFactory()($container, PageFactoryFactory::class);
    }

    private static function released(): Root
    {
        return new Root(
            path   : '/bus/1',
            mount  : '/message-bus/v1/',
            release: new Release(
                component: new Component('message-bus'),
                version  : Version::fromTag('v1.0.0') ?? self::fail('The tag is not a version.'),
            ),
        );
    }

    /**
     * The real helper over the real router, for the same reason the link tests use it:
     * the URL a page carries is meant to come from the router, so a stub would assert
     * the expectation instead of the behaviour.
     *
     * Every document these tests build a page for is registered the way a build
     * registers one: at its published path, with no name.
     */
    private static function url(DocumentRoot $root): DocumentRoute
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
        $router->addRoute(new Route(
            path      : "{$root->basePath}{path}",
            middleware: $middleware,
            name      : DocumentRoute::NAME,
        ));

        return new DocumentRoute($root, new UrlHelper($router));
    }

    #[Test]
    public function addsTheComponentAndVersionToADocumentOfAReleasedComponent(): void
    {
        static::assertSame(
            'Setup | Message Bus v1',
            self::pages(roots: [self::released()])(
                localPath: 'message-bus/v1/guide/setup.md',
                label    : 'Setup',
            )->titleSegment,
        );
    }

    #[Test]
    public function capturesTheDeploymentIdentityForEveryPage(): void
    {
        $pages = self::pages([
            'siteName'     => 'CommonDoc',
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

        static::assertSame('CommonDoc', $first->siteName);
        static::assertSame('1.0', $first->versionLabel);

        // The identity is shared; only what differs per document is not.
        static::assertSame($first->siteName, $second->siteName);
        static::assertSame($first->versionLabel, $second->versionLabel);
        static::assertNotSame($first->url, $second->url);
    }

    #[Test]
    public function carriesTheReleaseThePageBelongsTo(): void
    {
        $page = self::pages(roots: [self::released()])(
            localPath: 'message-bus/v1/guide/setup.md',
            label    : 'Setup',
        );

        static::assertNotNull($page->release);
        static::assertSame('Message Bus', $page->release->component->label);
        static::assertSame(1, $page->release->version->major);
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
    public function contributesNothingToTheTitleForTheRootIndex(): void
    {
        static::assertSame(
            '',
            self::pages()(
                localPath: 'README.md',
                label    : 'Readme',
            )->titleSegment,
        );
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
        // with no site name is cosmetic, a build that cannot start is not.
        $page = self::pages(root: new DocumentRoot('/'))(
            localPath: 'index.md',
            label    : 'Home',
        );

        static::assertSame('', $page->siteName);
        static::assertSame('', $page->versionLabel);
        static::assertSame('/', $page->url);
    }

    #[Test]
    public function namesOnlyTheComponentAndVersionForADirectoryIndex(): void
    {
        // `Index` repeats what the address already says.
        static::assertSame(
            'Message Bus v1',
            self::pages(roots: [self::released()])(
                localPath: 'message-bus/v1/README.md',
                label    : 'Readme',
            )->titleSegment,
        );
    }

    #[Test]
    public function namesTheDirectoryForAnIndexOutsideAReleasedComponent(): void
    {
        static::assertSame(
            'Concepts',
            self::pages()(
                localPath: 'concepts/README.md',
                label    : 'Readme',
            )->titleSegment,
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

    #[Test]
    public function titlesADocumentWithItsOwnLabel(): void
    {
        static::assertSame(
            'Setup',
            self::pages()(
                localPath: 'guide/setup.md',
                label    : 'Setup',
            )->titleSegment,
        );
    }
}
