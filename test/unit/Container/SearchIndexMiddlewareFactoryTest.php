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

use CommonDoc\Container\SearchIndexMiddlewareFactory;
use CommonDoc\Middleware\SearchIndexMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Helper\UrlHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type\Exception\CoercionException;

#[CoversClass(SearchIndexMiddlewareFactory::class)]
#[CoversMethod(SearchIndexMiddlewareFactory::class, '__invoke')]
final class SearchIndexMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheMiddlewareFromTheRoute(): void
    {
        $route = new DocumentRoute(new DocumentRoot('/'), $this->createStub(UrlHelper::class));

        $subject = new SearchIndexMiddlewareFactory()(
            new ServiceManager(['factories' => [DocumentRoute::class => static fn(): DocumentRoute => $route]]),
            SearchIndexMiddleware::class,
        );

        static::assertInstanceOf(SearchIndexMiddleware::class, $subject);
    }

    #[Test]
    public function refusesARouteThatIsNotADocumentRoute(): void
    {
        $this->expectException(CoercionException::class);

        new SearchIndexMiddlewareFactory()(
            new ServiceManager(['factories' => [DocumentRoute::class => static fn(): string => 'not a route']]),
            SearchIndexMiddleware::class,
        );
    }
}
