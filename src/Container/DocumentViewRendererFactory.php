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
use Laminas\ServiceManager\Factory\FactoryInterface;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Construction seam for the document view renderer.
 *
 * The template renderer is the framework's, so the layout, the resolver chain
 * and every view helper come from the host container rather than from here.
 *
 * @internal
 */
final readonly class DocumentViewRendererFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): DocumentViewRenderer {
        return new DocumentViewRenderer($container->get(TemplateRendererInterface::class));
    }
}
