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

use CommonDoc\View\Dropdown;
use CommonDoc\View\Helper\ComponentMenu;
use Laminas\Escaper\EscaperInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\HelperPluginManager;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Navigation\View\Helper\Navigation;

/**
 * Construction seam for the navbar's components menu.
 *
 * The navigation helper is resolved from the plugin manager rather than the root container,
 * because that is where `webware-navigation` registers it: it is a view helper and nothing
 * else, so it has no service of its own to fetch.
 *
 * @internal
 */
final readonly class ComponentMenuFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     *
     * @throws ContainerExceptionInterface When a collaborator cannot be fetched
     * @throws NotFoundExceptionInterface When a collaborator is not registered
     * @throws Type\Exception\ExceptionInterface When a collaborator is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ComponentMenu {
        $helpers = Type\instance_of(HelperPluginManager::class)
            ->coerce($container->get(HelperPluginManager::class));

        return new ComponentMenu(
            navigation: Type\instance_of(Navigation::class)->coerce($helpers->get(Navigation::class)),
            dropdown  : new Dropdown(
                Type\instance_of(EscaperInterface::class)
                    ->coerce($container->get(EscaperInterface::class)),
            ),
        );
    }
}
