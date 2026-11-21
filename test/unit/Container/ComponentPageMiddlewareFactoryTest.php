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

use CommonDoc\Container\ComponentPageMiddlewareFactory;
use CommonDoc\Middleware\ComponentPageMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type\Exception\CoercionException;

#[CoversClass(ComponentPageMiddlewareFactory::class)]
#[CoversMethod(ComponentPageMiddlewareFactory::class, '__invoke')]
final class ComponentPageMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheMiddlewareFromTheRouteAndTheRenderer(): void
    {
        $route = new DocumentRoute(new DocumentRoot('/'), $this->createStub(UrlHelper::class));

        $subject = new ComponentPageMiddlewareFactory()($this->container($route), ComponentPageMiddleware::class);

        static::assertInstanceOf(ComponentPageMiddleware::class, $subject);
    }

    #[Test]
    public function refusesARouteThatIsNotADocumentRoute(): void
    {
        $this->expectException(CoercionException::class);

        new ComponentPageMiddlewareFactory()($this->container('not a route'), ComponentPageMiddleware::class);
    }

    private function container(mixed $route): ServiceManager
    {
        return new ServiceManager([
            'factories' => [
                DocumentRoute::class             => static fn(): mixed => $route,
                TemplateRendererInterface::class => fn(): TemplateRendererInterface => $this->createStub(
                    TemplateRendererInterface::class,
                ),
            ],
        ]);
    }
}
