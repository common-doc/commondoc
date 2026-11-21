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

namespace CommonDoc\Container;

use Closure;
use CommonDoc\CommonMarkEvent;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\LinkRewriter;
use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\HeadingListener;
use CommonDoc\View\PageContextListener;
use Laminas\ServiceManager\Exception\ExceptionInterface as ServiceManagerException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Exception\AlreadyInitializedException;
use League\CommonMark\MarkdownConverter;
use Mezzio\Helper\UrlHelper;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Builds the function a build calls to get its converter.
 *
 * It is registered under its own class name, and what the container serves for that
 * name is the callable it returns, not an instance of this class. A build needs one
 * converter of its own: the link rewriter is bound to that build's document and asset
 * lists, and a converter shared between builds would carry the previous build's. So the
 * handler is handed a function rather than a converter, and the function builds a fresh
 * environment each time it is called.
 *
 * Every collaborator is resolved here, once, and captured by value. The callable never
 * sees the container, which is the point: the handler that calls it constructs nothing
 * through a locator, and nothing it is given can reach back into the container to ask
 * for something this factory did not hand it.
 *
 * The callable is static, so it cannot capture `$this` or anything not listed in its
 * `use` clause. What it can do is fixed by that clause.
 *
 * @internal
 */
final readonly class ConverterFactoryFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     *
     * @return Closure(list<non-empty-string> $documents, list<non-empty-string> $assets): ConverterInterface
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Type\Exception\ExceptionInterface When a collaborator is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Closure {
        $environmentOptions = EnvironmentFactory::optionsFrom($container->get('config'));

        $manager = Type\instance_of(ExtensionPluginManager::class)
            ->coerce($container->get(ExtensionPluginManager::class));
        $renderer = Type\instance_of(DocumentViewRenderer::class)
            ->coerce($container->get(DocumentViewRenderer::class));
        $pageListener = Type\instance_of(PageContextListener::class)
            ->coerce($container->get(PageContextListener::class));
        $headingListener = Type\instance_of(HeadingListener::class)
            ->coerce($container->get(HeadingListener::class));
        $root = Type\instance_of(DocumentRoot::class)
            ->coerce($container->get(DocumentRoot::class));
        $url = Type\instance_of(UrlHelper::class)
            ->coerce($container->get(UrlHelper::class));

        return /**
         * @param list<non-empty-string> $documents Every document the scan found
         * @param list<non-empty-string> $assets    Every path the scan found that is not one
         *
         * @throws AlreadyInitializedException When the environment is already extended
         * @throws ServiceManagerException When the plugin manager cannot serve a default extension
         */ static function (array $documents, array $assets) use (
            $environmentOptions,
            $manager,
            $renderer,
            $pageListener,
            $headingListener,
            $root,
            $url,
        ): ConverterInterface {
            $environment = EnvironmentFactory::assemble(
                options        : $environmentOptions,
                manager        : $manager,
                renderer       : $renderer,
                pageListener   : $pageListener,
                headingListener: $headingListener,
            );

            $environment->addEventListener(
                CommonMarkEvent::DocumentParsed->value,
                new LinkRewriter($root, $url, $documents, $assets),
            );

            return new MarkdownConverter($environment);
        };
    }
}
