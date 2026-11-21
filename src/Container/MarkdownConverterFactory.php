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
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the shared Markdown converter.
 *
 * It returns a converter over the shared environment: the option surface stays in
 * config and this class only wires the pair together. `CommonMarkConverter` cannot
 * be used here, because it is final and constructs its own environment, whereas
 * `MarkdownConverter` accepts the injected one.
 *
 * This converter has no link rewriter, so a link keeps the target exactly as written.
 * A build does not use it: a build needs a converter bound to its own document and
 * asset lists, which `ConverterFactoryFactory` supplies as a callable so that no
 * caller has to ask a container for one while it runs.
 *
 * @internal
 */
final readonly class MarkdownConverterFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
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
    ): MarkdownConverter {
        $environment = Type\instance_of(EnvironmentInterface::class)
            ->coerce($container->get(EnvironmentInterface::class));

        return new MarkdownConverter($environment);
    }
}
