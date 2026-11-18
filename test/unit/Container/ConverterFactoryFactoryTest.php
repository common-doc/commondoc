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
use CommonDoc\Container\ConverterFactoryFactory;
use CommonDoc\Container\EnvironmentFactory;
use CommonDoc\Container\ExtensionPluginManager;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use League\CommonMark\ConverterInterface;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * The per-build converter, proved by converting something.
 *
 * Behaviour rather than structure: `MarkdownConverter` keeps no accessor for the
 * environment it was given, so the only honest way to show that a rewriter bound
 * to one build's lists ran is to convert a document that has an internal link
 * and read the output. A link to a document the lists do not contain fails,
 * which is what makes the second test a proof that the lists are per-build.
 */
#[CoversClass(ConverterFactoryFactory::class)]
#[CoversMethod(ConverterFactoryFactory::class, '__invoke')]
final class ConverterFactoryFactoryTest extends TestCase
{
    #[Test]
    public function buildsAFactoryThatReturnsAConverter(): void
    {
        $factory = new ConverterFactoryFactory()($this->container(), ConverterFactoryFactory::class);

        static::assertInstanceOf(Closure::class, $factory);
        static::assertInstanceOf(ConverterInterface::class, $factory([], []));
    }

    #[Test]
    public function refusesADocumentRootThatIsNotOne(): void
    {
        $this->expectException(Type\Exception\ExceptionInterface::class);

        new ConverterFactoryFactory()($this->container(root: 'not a root'), ConverterFactoryFactory::class);
    }

    #[Test]
    public function refusesALinkTheListsOfThisCallDoNotContain(): void
    {
        // The same source, through a converter built for a build that never
        // discovered the target. A shared converter would have answered with the
        // previous list; a per-build one cannot.
        $converter = $this->factory()(['guide/setup.md'], []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('which resolves to "guide/intro.md"');

        $converter->convert('[intro](intro.md)');
    }

    #[Test]
    public function rewritesALinkAgainstTheListsThisCallWasGiven(): void
    {
        $converter = $this->factory()(
            ['guide/intro.md', 'guide/setup.md'],
            [],
        );

        $html = (string) $converter->convert('[intro](intro.md)');

        static::assertStringContainsString('/guide/intro/', $html);
    }

    private function container(mixed $root = null): ContainerInterface
    {
        $context = new PageContext();
        $context->set(new Page(
            label       : 'Setup',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'guide/setup.md',
            url         : '/guide/setup/',
            versionLabel: '1.0',
        ));

        $renderer = $this->createStub(TemplateRendererInterface::class);
        $renderer
            ->method('render')
            ->willReturnCallback(
                static fn(string $template, array $params = []): string => (string) ($params['documentBody'] ?? ''),
            );

        $pluginManager = new ExtensionPluginManager(
            $this->createStub(ContainerInterface::class),
            [],
        );

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(static fn(string $id): mixed => match ($id) {
                'config'                      => [],
                DocumentRoot::class           => $root ?? new DocumentRoot('/'),
                EnvironmentFactory::class     => new EnvironmentFactory(),
                ExtensionPluginManager::class => $pluginManager,
                DocumentViewRenderer::class   => new DocumentViewRenderer($renderer),
                PageContextListener::class    => new PageContextListener($context),
                default                       => null,
            });

        return $container;
    }

    private function factory(): Closure
    {
        return new ConverterFactoryFactory()($this->container(), ConverterFactoryFactory::class);
    }
}
