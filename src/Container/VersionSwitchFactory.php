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
use CommonDoc\View\Helper\VersionSwitch;
use CommonDoc\View\PageContext;
use Laminas\Escaper\EscaperInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the navbar's version menu.
 *
 * The page comes from the context the build already keeps, which is still populated while
 * the layout renders: the layout is rendered inside the conversion, and the context is
 * cleared only after the conversion returns.
 *
 * @internal
 */
final readonly class VersionSwitchFactory implements FactoryInterface
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
    ): VersionSwitch {
        return new VersionSwitch(
            context : Type\instance_of(PageContext::class)->coerce($container->get(PageContext::class)),
            dropdown: new Dropdown(
                Type\instance_of(EscaperInterface::class)
                    ->coerce($container->get(EscaperInterface::class)),
            ),
        );
    }
}
