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

use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\PathType;
use InvalidArgumentException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_array;

/**
 * Construction seam for the document root.
 *
 * The root is the site's deployment, so it comes from configuration rather than
 * from a caller: the base path differs between a custom domain, an organization
 * site and a project site, and no code above this factory should know which. The
 * defaults are the custom-domain case, which is also what a build with no
 * configuration at all should produce.
 *
 * The section is keyed by `DocumentRoot::class`, the same way the CommonMark
 * environment is keyed by `EnvironmentInterface::class`, so a deployment
 * overrides these values in its own autoload file without touching this package.
 *
 * Parsing is separated from validating: `Psl\Type` shape specs assert what the
 * config must be, and a missing or malformed section is caught and replaced with
 * the defaults rather than refusing the build. The refusal that matters is
 * `DocumentRoot` itself rejecting a base path it cannot prefix with, which is a
 * statement about the value rather than about where it came from.
 *
 * @internal
 */
final readonly class DocumentRootFactory implements FactoryInterface
{
    private const string DEFAULT_BASE_PATH = '/';

    /**
     * The section this factory reads, or the defaults when it is absent.
     *
     * `matches()` is asked before `coerce()` so a malformed section falls back
     * instead of throwing: a deployment with a typo in one key should still
     * build, and `DocumentRoot` will refuse the value if it is genuinely
     * unusable.
     *
     * @return array{basePath: string, pathType: PathType}
     *
     * @throws Type\Exception\ExceptionInterface When the section does not match the spec
     */
    private static function section(mixed $config): array
    {
        $defaults = [
            'basePath' => self::DEFAULT_BASE_PATH,
            'pathType' => PathType::Directory,
        ];

        // Guarded rather than early-returned: a non-array config means there is
        // no section to read, and the shape check below then falls back. One
        // fallback path instead of two, so there is no branch that can be
        // removed without changing behaviour.
        /** @var mixed $section Deliberately untyped: the shape check decides what it is */
        $section = is_array($config) ? $config[DocumentRoot::class] ?? null : null;

        $spec = Type\shape([
            'basePath' => Type\string(),
            'pathType' => Type\instance_of(PathType::class),
        ]);

        return $spec->matches($section) ? $spec->coerce($section) : $defaults;
    }

    /**
     * @param ?array<array-key, mixed> $options
     *
     * @throws ContainerExceptionInterface When the config service cannot be fetched
     * @throws InvalidArgumentException When the configured base path cannot prefix a path
     * @throws NotFoundExceptionInterface When the config service is not registered
     * @throws Type\Exception\ExceptionInterface When the section does not match the spec
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): DocumentRoot {
        $section = self::section($container->get('config'));

        return new DocumentRoot(
            basePath: $section['basePath'],
            pathType: $section['pathType'],
        );
    }
}
