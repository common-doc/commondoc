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

use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\MarkdownConverter;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the Markdown converter.
 *
 * The converter takes the environment the container built, so the option
 * surface stays in config and this class only wires the pair together.
 * CommonMarkConverter cannot be used here: it is final and constructs its own
 * environment, whereas MarkdownConverter accepts the injected one.
 *
 * @internal
 */
final readonly class MarkdownConverterFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): MarkdownConverter {
        $environment = $container->get(EnvironmentInterface::class);

        return new MarkdownConverter($environment);
    }
}
