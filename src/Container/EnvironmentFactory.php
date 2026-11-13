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

use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\PageContextListener;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Event\DocumentPreRenderEvent;
use League\CommonMark\Exception\AlreadyInitializedException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\Document;
use Override;
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
 * The document node is then pointed at the view renderer at priority 10, so it
 * outranks the core HTML renderer for the same class and the whole document is
 * rendered as a view rather than as finished HTML. The page context listener is
 * registered on the pre-render event, which is where the page reaches the
 * document's own data. Both collaborators come from the container.
 *
 * @internal
 */
final readonly class EnvironmentFactory implements FactoryInterface
{
    /**
     * @return array<string, mixed>
     */
    private function optionsFrom(mixed $config): array
    {
        if (! is_array($config) || ! is_array($config[EnvironmentInterface::class] ?? null)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $config[EnvironmentInterface::class];
    }

    /**
     * @param ?array<array-key, mixed> $options
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws AlreadyInitializedException
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): EnvironmentInterface {
        $manager = $container->get(ExtensionPluginManager::class);

        $environment = new Environment($this->optionsFrom($container->get('config')));
        $environment->addExtension(new CommonMarkCoreExtension());

        foreach ($manager->getDefaultExtensions() as $name) {
            /** @var ExtensionInterface $extension */
            $extension = $manager->get($name);

            $environment->addExtension($extension);
        }

        $environment->addRenderer(Document::class, $container->get(DocumentViewRenderer::class), 10);
        $environment->addEventListener(
            DocumentPreRenderEvent::class,
            $container->get(PageContextListener::class),
        );

        return $environment;
    }
}
