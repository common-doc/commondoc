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
use CommonDoc\Container\ConverterFactoryFactory;
use CommonDoc\Container\PageFactoryFactory;
use CommonDoc\Http\Middleware\NotServedMiddleware;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use League\CommonMark\ConverterInterface;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function sprintf;

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
    public function refusesACollaboratorOfTheWrongType(): void
    {
        // A handler without a collector cannot register a route, so a wrong
        // service has to fail here with a typed message rather than at the first
        // document.
        $this->expectException(Type\Exception\ExceptionInterface::class);

        new BuildSiteHandlerFactory()($this->container('not a collector'), BuildSiteHandler::class);
    }

    private function container(mixed $collector = null): ContainerInterface
    {
        $collector ??= new RouteCollector($this->createStub(RouterInterface::class));

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(static fn(string $id): mixed => match ($id) {
                DocumentRoot::class            => new DocumentRoot('/'),
                RouteCollectorInterface::class => $collector,
                PageContext::class             => new PageContext(),
                NotServedMiddleware::class     => new NotServedMiddleware(),
                ConverterFactoryFactory::class => static fn(
                    array $documents,
                    array $assets,
                ): ConverterInterface => throw new RuntimeException('The converter is not used here.'),
                PageFactoryFactory::class      => static fn(string $path, string $label): Page => new Page(
                    label       : $label,
                    projectName : 'CommonDoc',
                    projectSlug : 'commondoc',
                    sourcePath  : $path,
                    url         : '/',
                    versionLabel: '1.0',
                ),
                default                        => throw new RuntimeException(sprintf('Unexpected service "%s".', $id)),
            });

        return $container;
    }
}
