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

use CommonDoc\CommonMarkEvent;
use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\HeadingListener;
use CommonDoc\View\PageContextListener;
use Laminas\ServiceManager\Exception\ExceptionInterface as ServiceManagerException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Exception\AlreadyInitializedException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\Document;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_array;

/**
 * Construction seam for the Markdown environment.
 *
 * The environment options come from the config's EnvironmentInterface section,
 * so the option surface lives in config/autoload/commonmark.global.php rather
 * than in this class.
 *
 * Extensions are added core first, then every name the plugin manager serves, in
 * the order the manager reports them. The manager owns that list, because it is
 * constructed with the const defaults already merged into the application's
 * section, so this class never re-reads the section and cannot load a name twice.
 *
 * The document node is then pointed at the view renderer, so a document renders
 * as a view rather than as finished HTML. It is the only renderer registered for
 * that node, so no priority is needed and none is passed: a priority would only
 * matter if something else also claimed `Document`, and nothing does. The page
 * context listener is registered on the parsed event, which is where the page
 * reaches the document's own data early enough for a listener that resolves a
 * relative link to read it. Both collaborators come from the container.
 *
 * @internal
 */
final readonly class EnvironmentFactory implements FactoryInterface
{
    /**
     * A fresh environment from collaborators that are already resolved.
     *
     * Static and container free, so a caller that has to build an environment many
     * times, as a build does for each converter, resolves its collaborators once and
     * calls this per environment rather than holding the container to ask again.
     * `__invoke()` below is the same assembly with the collaborators fetched.
     *
     * @param array<string, mixed> $options
     *
     * @throws AlreadyInitializedException
     * @throws ServiceManagerException When the plugin manager cannot serve a default extension
     */
    public static function assemble(
        array $options,
        ExtensionPluginManager $manager,
        DocumentViewRenderer $renderer,
        PageContextListener $pageListener,
        HeadingListener $headingListener,
    ): Environment {
        $environment = new Environment($options);
        $environment->addExtension(new CommonMarkCoreExtension());

        foreach ($manager->getDefaultExtensions() as $name) {
            /** @var ExtensionInterface $extension */
            $extension = $manager->get($name);

            $environment->addExtension($extension);
        }

        $environment->addRenderer(Document::class, $renderer);
        // No priority, for the same reason the renderer takes none: the only
        // ordering that matters is that the page is written before a listener
        // that rewrites a link can read it, and every listener the build adds is
        // added to an environment this factory has already finished. Listeners
        // of equal priority run in the order they were added, so nothing else
        // has to be arranged.
        $environment->addEventListener(CommonMarkEvent::DocumentParsed->value, $pageListener);
        // Below the page listener and below the extensions, so every heading already
        // carries the id the renderer will emit. Its own priority rather than addition
        // order, because an extension registering at the same priority would be decided by
        // registration order instead of by intent.
        $environment->addEventListener(
            CommonMarkEvent::DocumentParsed->value,
            $headingListener,
            HeadingListener::PRIORITY,
        );

        return $environment;
    }

    /**
     * The environment options from the aggregated configuration.
     *
     * @return array<string, mixed>
     */
    public static function optionsFrom(mixed $config): array
    {
        if (! is_array($config) || ! is_array($config[EnvironmentInterface::class] ?? null)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $config[EnvironmentInterface::class];
    }

    /**
     * A fresh environment, so a caller can register its own listeners on it.
     *
     * The declared type is the concrete `Environment` rather than the interface,
     * because adding a listener is only on the builder interface, and the caller
     * that wants that is a build registering its link rewriter. The container
     * still serves this under `EnvironmentInterface`, which resolves either way.
     *
     * @param ?array<array-key, mixed> $options
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws AlreadyInitializedException
     * @throws ServiceManagerException When the plugin manager cannot serve a default extension
     * @throws Type\Exception\ExceptionInterface When a collaborator is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Environment {
        return self::assemble(
            options        : self::optionsFrom($container->get('config')),
            manager        : Type\instance_of(ExtensionPluginManager::class)
                ->coerce($container->get(ExtensionPluginManager::class)),
            renderer       : Type\instance_of(DocumentViewRenderer::class)
                ->coerce($container->get(DocumentViewRenderer::class)),
            pageListener   : Type\instance_of(PageContextListener::class)
                ->coerce($container->get(PageContextListener::class)),
            headingListener: Type\instance_of(HeadingListener::class)
                ->coerce($container->get(HeadingListener::class)),
        );
    }
}
