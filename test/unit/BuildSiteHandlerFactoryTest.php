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

use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\Container\BuildSiteHandlerFactory;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\PageContext;
use Laminas\ServiceManager\ServiceManager;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The handler's collaborators, resolved from a real container.
 *
 * A real `ServiceManager` rather than a stub, because one of the collaborators
 * is the locator itself and the factory coerces the container it is given to
 * `ServiceLocatorInterface`: a `ContainerInterface` stub does not satisfy that,
 * which is the check working as intended.
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

    /**
     * A container with only what the handler's own constructor needs.
     *
     * The per-build objects are absent on purpose: they are asked for with
     * `build()` while a build runs, so a container that could not supply one is
     * exactly what an over-eager constructor would fail on.
     */
    private function container(): ServiceManager
    {
        return new ServiceManager([
            'factories' => [
                DocumentRoot::class            => static fn(): DocumentRoot => new DocumentRoot('/'),
                PageContext::class             => static fn(): PageContext => new PageContext(),
                NotServedMiddleware::class     => static fn(): NotServedMiddleware => new NotServedMiddleware(),
                RouteCollectorInterface::class => fn(): RouteCollector => new RouteCollector(
                    $this->createStub(RouterInterface::class),
                ),
                EnvironmentInterface::class    => static fn(): Environment => new Environment(),
            ],
        ]);
    }
}
