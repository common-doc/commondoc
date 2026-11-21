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

use CommonDoc\Container\DocumentRouteFactory;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\DocumentRoute;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Helper\UrlHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type\Exception\CoercionException;

#[CoversClass(DocumentRouteFactory::class)]
#[CoversMethod(DocumentRouteFactory::class, '__invoke')]
final class DocumentRouteFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheRouteFromTheRootAndTheHelper(): void
    {
        $subject = new DocumentRouteFactory()($this->container(new DocumentRoot('/repo')), DocumentRoute::class);

        static::assertSame('/repo/{path}', $subject->pattern());
    }

    #[Test]
    public function refusesARootThatIsNotADocumentRoot(): void
    {
        $this->expectException(CoercionException::class);

        new DocumentRouteFactory()($this->container('not a root'), DocumentRoute::class);
    }

    private function container(mixed $root): ServiceManager
    {
        return new ServiceManager([
            'factories' => [
                DocumentRoot::class => static fn(): mixed => $root,
                UrlHelper::class    => fn(): UrlHelper => $this->createStub(UrlHelper::class),
            ],
        ]);
    }
}
