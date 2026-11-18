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
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\LinkRewriter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\MarkdownConverter;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Produces the callable that builds a converter for one build.
 *
 * A converter cannot be a container service once link rewriting is involved,
 * because the rewriter is bound to the documents and assets that one build
 * discovered. A shared converter would have to be given those lists after the
 * fact, which is shared mutable state, or be built once with a stale list.
 *
 * So the container holds the thing that makes converters, and the callable
 * captures nothing that varies. Each call builds a fresh environment and a
 * fresh converter, which is the shape the owner asked for: one environment and
 * one converter per build. That also means two builds in one process cannot see
 * each other's listeners, which is what makes running each repository's build in
 * its own process a later option rather than a change of design.
 *
 * The environment is not constructed here. `EnvironmentFactory` already owns
 * that, and it is invoked directly rather than fetched from the container: the
 * container caches its result, and a cached environment is exactly what this
 * type exists to avoid. Invoking the factory twice yields two environments.
 *
 * @internal
 */
final readonly class ConverterFactoryFactory implements FactoryInterface
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
    ): Closure {
        // Coerced rather than checked, for the same reason the page factory
        // coerces its own answers: a converter cannot be built without the root,
        // so a wrong service should fail at construction with a typed message
        // rather than at the first document.
        $root = Type\instance_of(DocumentRoot::class)
            ->coerce($container->get(DocumentRoot::class));

        // Composed directly rather than fetched: the container entry for
        // `EnvironmentFactory` is a service that returns an environment, which is
        // exactly the shared object this type exists to avoid. This factory holds
        // no state, so composing it here is the whole dependency.
        $environment = new EnvironmentFactory();

        /**
         * Build one converter for one build.
         *
         * The two lists are the only per-build inputs, so they are the only
         * parameters. The environment is built inside the call rather than
         * captured, which is what keeps two builds independent.
         *
         * @param list<non-empty-string> $documents Every document the scan discovered
         * @param list<non-empty-string> $assets    Every path that is not a document
         *
         * @throws \League\CommonMark\Exception\AlreadyInitializedException
         *         When the environment has already been extended
         * @throws \Psr\Container\ContainerExceptionInterface
         *         When an extension cannot be fetched
         * @throws \Psr\Container\NotFoundExceptionInterface
         *         When an extension is not registered
         */
        // @mago-expect lint:inline-variable-return - the assignment is what
        // carries the docblock declaring the throws; inlining it would drop them.
        $factory = static function (
            array $documents,
            array $assets,
        ) use ($container, $environment, $root): ConverterInterface {
            $built = $environment($container, Environment::class);

            $built->addEventListener(
                DocumentParsedEvent::class,
                new LinkRewriter($root, $documents, $assets),
            );

            return new MarkdownConverter($built);
        };

        return $factory;
    }
}
