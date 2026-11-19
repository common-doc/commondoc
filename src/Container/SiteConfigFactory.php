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
use CommonDoc\SiteConfig;
use InvalidArgumentException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_array;
use function is_string;

/**
 * Construction seam for the site, and the only reader of its section.
 *
 * The section is keyed by `SiteConfig::class`, the same way the CommonMark
 * environment is keyed by `EnvironmentInterface::class`, so a deployment states
 * its own values in its own autoload file without touching this package.
 *
 * Reading is separated from validating. A key that is absent or of the wrong type
 * is replaced with its default rather than refusing the build, because a
 * deployment with one typo should still build, and it costs one setting instead of
 * all of them. The refusal that matters is `DocumentRoot` rejecting a base path it
 * cannot prefix with, which is a statement about the value rather than about where
 * it came from, so it is left to the type that owns it.
 *
 * @internal
 */
final readonly class SiteConfigFactory implements FactoryInterface
{
    private const string DEFAULT_BASE_PATH = '/';

    /**
     * The output layout, or the directory layout when the configured value is not
     * one of the two the type defines.
     *
     * @param array<array-key, mixed> $section
     */
    private static function pathType(array $section): PathType
    {
        /** @var mixed $value Deliberately untyped: the check below decides what it is */
        $value = $section['pathType'] ?? null;

        return $value instanceof PathType ? $value : PathType::Directory;
    }

    /**
     * The section this factory reads, with each key falling back on its own.
     *
     * Per key rather than per section: a whole-section fallback would discard a
     * correctly written base path because a different key was missing or wrong,
     * which is the one outcome a deployment cannot detect. Each value is taken
     * when it has the right type and replaced with its default when it does not,
     * so a single typo costs one setting instead of all of them.
     *
     * @return array{basePath: string, pathType: PathType, projectName: string, projectSlug: string, versionLabel: string}
     */
    private static function section(mixed $config): array
    {
        $section = self::sectionOf($config);

        return [
            'basePath'     => self::text(
                section: $section,
                key    : 'basePath',
                default: self::DEFAULT_BASE_PATH,
            ),
            'pathType'     => self::pathType(section: $section),
            'projectName'  => self::text(
                section: $section,
                key    : 'projectName',
            ),
            'projectSlug'  => self::text(
                section: $section,
                key    : 'projectSlug',
            ),
            'versionLabel' => self::text(
                section: $section,
                key    : 'versionLabel',
            ),
        ];
    }

    /**
     * The configured section itself, or nothing when there is not one.
     *
     * The array guard is what keeps a scalar configuration from being indexed: `??`
     * suppresses a missing key but a string offset with a non-numeric key is a
     * different question. A caller that supplies a non-array is answered with the
     * defaults rather than a TypeError.
     *
     * @return array<array-key, mixed>
     *
     * @infection-ignore-all Equivalent mutant: with the guard removed, `??` still
     * answers null for a scalar because it tests with isset(), so the removal
     * reaches the same return. No test can distinguish the two, and the guard is
     * kept because it states the intent rather than because it changes a result.
     */
    private static function sectionOf(mixed $config): array
    {
        if (! is_array($config)) {
            return [];
        }

        /** @var mixed $value Deliberately untyped: either a section or not */
        $value = $config[SiteConfig::class] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * One key read as a string, or the default when it is absent or of no use.
     *
     * @param array<array-key, mixed> $section
     */
    private static function text(array $section, string $key, string $default = ''): string
    {
        /** @var mixed $value Deliberately untyped: the check below decides what it is */
        $value = $section[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param ?array<array-key, mixed> $options
     *
     * @throws ContainerExceptionInterface When the config service cannot be fetched
     * @throws InvalidArgumentException When the configured base path cannot prefix a path
     * @throws NotFoundExceptionInterface When the config service is not registered
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): SiteConfig {
        $section = self::section($container->get('config'));

        return new SiteConfig(
            root        : new DocumentRoot(
                basePath: $section['basePath'],
                pathType: $section['pathType'],
            ),
            projectName : $section['projectName'],
            projectSlug : $section['projectSlug'],
            versionLabel: $section['versionLabel'],
        );
    }
}
