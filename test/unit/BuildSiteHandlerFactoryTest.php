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

use Closure;
use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\Container\BuildSiteHandlerFactory;
use CommonDoc\Container\ConverterFactoryFactory;
use CommonDoc\Container\PageFactoryFactory;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\View\PageContext;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type\Exception\CoercionException;

/**
 * The handler's collaborators, resolved from a real container.
 *
 * The two per-build objects are served as callables, under the names of the factories
 * that produce them, so what the handler is handed is a function and not a container.
 */
#[CoversClass(BuildSiteHandlerFactory::class)]
#[CoversMethod(BuildSiteHandlerFactory::class, '__invoke')]
final class BuildSiteHandlerFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheHandler(): void
    {
        $subject = new BuildSiteHandlerFactory()($this->container(), BuildSiteHandler::class);

        static::assertInstanceOf(BuildSiteHandler::class, $subject);
    }

    #[Test]
    public function refusesAConverterThatIsNotACallable(): void
    {
        $this->expectException(CoercionException::class);

        new BuildSiteHandlerFactory()(
            $this->container(converter: 'not a closure'),
            BuildSiteHandler::class,
        );
    }

    /**
     * A container with only what the handler's own constructor needs.
     *
     * The converter and the page are served as callables and are not called: building the
     * handler must not build either, so a container whose callables fail if invoked is
     * exactly what an over-eager constructor would trip on.
     */
    private function container(mixed $converter = null): ServiceManager
    {
        $unused = static function (): never {
            self::fail('Building the handler must not call a per-build function.');
        };

        return new ServiceManager([
            'factories' => [
                DocumentRoot::class            => static fn(): DocumentRoot => new DocumentRoot('/'),
                DocumentRoute::class           => fn(): DocumentRoute => new DocumentRoute(
                    new DocumentRoot('/'),
                    $this->createStub(UrlHelper::class),
                ),
                PageContext::class             => static fn(): PageContext => new PageContext(),
                NotServedMiddleware::class     => static fn(): NotServedMiddleware => new NotServedMiddleware(),
                RouteCollectorInterface::class => fn(): RouteCollector => new RouteCollector(
                    $this->createStub(RouterInterface::class),
                ),
                ConverterFactoryFactory::class => static fn(): mixed => $converter ?? Closure::fromCallable($unused),
                PageFactoryFactory::class      => static fn(): Closure => Closure::fromCallable($unused),
            ],
        ]);
    }
}
