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
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\LinkRewriter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Exception\AlreadyInitializedException;
use League\CommonMark\MarkdownConverter;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the Markdown converter, in its two modes.
 *
 * Asked without options, it returns a converter over the shared environment:
 * the option surface stays in config and this class only wires the pair
 * together. `CommonMarkConverter` cannot be used here, because it is final and
 * constructs its own environment, whereas `MarkdownConverter` accepts the
 * injected one.
 *
 * Asked with a scan, it returns a converter over a **fresh** environment that
 * carries a `LinkRewriter` bound to that scan. The two lists are the only
 * per-build inputs, so they are the only options, and the caller asks for one
 * with `build()`: the service manager never caches what it builds, so a second
 * build cannot inherit the first build's listener. That is the whole mechanism,
 * and it is why there is no callable type here to own the difference.
 *
 * The environment is built through the same service manager rather than through
 * a captured factory, so the extension list and the page listener are exactly
 * what the shared environment has. Only the rewriter differs.
 *
 * @internal
 */
final readonly class MarkdownConverterFactory implements FactoryInterface
{
    /**
     * The scan a build passes in, or null when the request is the plain converter.
     *
     * A shape that does not match is treated as no scan rather than as an error.
     * The options argument is open to any caller, and a build is the only thing
     * that has lists to pass, so an unrecognised shape is a plain request.
     *
     * @param ?array<array-key, mixed> $options
     *
     * @return null|array{documents: list<non-empty-string>, assets: list<non-empty-string>}
     *
     * @throws Type\Exception\ExceptionInterface When a shape key holds the wrong type
     */
    private static function scanFrom(?array $options): ?array
    {
        $spec = Type\shape([
            'documents' => Type\vec(Type\non_empty_string()),
            'assets'    => Type\vec(Type\non_empty_string()),
        ]);

        return $spec->matches($options) ? $spec->coerce($options) : null;
    }

    /**
     * @param ?array<array-key, mixed> $options The scan a build discovered, when there is one
     *
     * @throws AlreadyInitializedException When the built environment is already extended
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
        $scan = self::scanFrom($options);

        if (null === $scan) {
            $environment = Type\instance_of(EnvironmentInterface::class)
                ->coerce($container->get(EnvironmentInterface::class));

            return new MarkdownConverter($environment);
        }

        $locator = Type\instance_of(ServiceLocatorInterface::class)->coerce($container);
        $root    = Type\instance_of(DocumentRoot::class)->coerce($container->get(DocumentRoot::class));

        // `build()` rather than `get()`, because the environment is a shared
        // service: a listener added to the shared one would outlive this build
        // and be consulted by the next, against a document list that no longer
        // exists.
        $environment = Type\instance_of(Environment::class)
            ->coerce($locator->build(EnvironmentInterface::class));

        $environment->addEventListener(
            CommonMarkEvent::DocumentParsed->value,
            new LinkRewriter($root, $scan['documents'], $scan['assets']),
        );

        return new MarkdownConverter($environment);
    }
}
