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

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use CommonDoc\Input\Target;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\PathType;
use CommonDoc\SiteConfig;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psl\Filesystem;
use Psl\Regex;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Construction seam for the site, and the only reader of its section.
 *
 * The section is keyed by `SiteConfig::class`, the same way the CommonMark
 * environment is keyed by `EnvironmentInterface::class`, so a deployment states
 * its own values in its own autoload file without touching this package.
 *
 * Reading is separated from validating. A value that is absent or of the wrong type
 * is replaced with its default rather than refusing the build, because a deployment
 * with one typo should still build, and it costs one setting instead of all of them.
 * The refusals that matter are left to the types that own them: `DocumentRoot`
 * rejects a base path it cannot prefix with, `Root` rejects a mount it cannot prefix
 * with, and `SiteConfig` rejects two roots that would publish at one path.
 * Reading the roots is `RootReader`'s job, because reading a list is a different
 * job from reading a scalar.
 *
 * One key is not read from this section at all. The base path belongs to
 * `view_helper_config.base_path`, because the `BasePath` helper answers with that
 * value when a template composes a link, and a second copy here would let the
 * paths a build writes drift away from the paths a template emits. A site
 * section that still states `basePath` is refused rather than read, so the stale key
 * cannot be silently beaten by a value the operator has forgotten about.
 *
 * @internal
 */
final readonly class SiteConfigFactory implements FactoryInterface
{
    private const string DEFAULT_BASE_PATH = '/';

    private const string DEFAULT_OUTPUT = 'build';

    /** Where Laminas view keeps the helper configuration. */
    private const string VIEW_HELPER_CONFIG = 'view_helper_config';

    /** The key `BasePath` answers with, inside {@see self::VIEW_HELPER_CONFIG}. */
    private const string BASE_PATH = 'base_path';

    /**
     * The path space, read from the view helper configuration.
     *
     * The base path is stated once, as `view_helper_config.base_path`, because that is
     * the value Laminas view's `BasePath` helper answers with when a template composes
     * a link. Reading it here rather than keeping a second copy in the site section is
     * what stops the paths a build writes from drifting away from the paths a
     * template emits: one value decides both.
     *
     * Laminas view declares the key with a `null` default, and a null is not a path,
     * so it is answered with `/` like any other unreadable value.
     */
    private static function basePath(mixed $config): string
    {
        /** @var mixed $helperConfig */
        $helperConfig = is_array($config) ? $config[self::VIEW_HELPER_CONFIG] ?? null : null;

        $helperConfig = is_array($helperConfig) ? $helperConfig : [];

        return self::text(
            section: $helperConfig,
            key    : self::BASE_PATH,
            default: self::DEFAULT_BASE_PATH,
        );
    }

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
     * @return array{roots: list<Root>, basePath: string, pathType: PathType, output: string, projectName: string, projectSlug: string, versionLabel: string}
     *
     * @throws InvalidArgumentException When a configured mount cannot prefix a path,
     *         or when the site section states a base path that belongs in the view
     *         helper configuration
     * @throws FileWriterException When a version's files cannot be written
     * @throws Filesystem\Exception\ExceptionInterface When a previous extraction cannot be cleared
     * @throws Regex\Exception\ExceptionInterface When a tag cannot be matched, which the fixed
     *         pattern makes unreachable
     * @throws Type\Exception\ExceptionInterface When the captured groups are not the shape the
     *         pattern promises, which the fixed pattern makes unreachable
     */
    private static function section(mixed $config): array
    {
        $section = self::sectionOf($config);

        if (array_key_exists('basePath', $section)) {
            throw new InvalidArgumentException(sprintf(
                'The site section states "basePath", which is read from "%s.%s" instead, because one value has to decide both the paths a build writes and the paths a template emits.',
                self::VIEW_HELPER_CONFIG,
                self::BASE_PATH,
            ));
        }

        return [
            'basePath'     => self::basePath($config),
            'pathType'     => self::pathType(section: $section),
            'output'       => self::text(
                section: $section,
                key    : 'output',
                default: self::DEFAULT_OUTPUT,
            ),
            'roots'        => new RootReader()->read(
                configured: $section['roots'] ?? null,
                fallback  : new Target(self::text(
                    section: $section,
                    key    : 'target',
                    default: Target::DEFAULT,
                )),
            ),
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
     * @throws InvalidArgumentException When the configured base path cannot prefix a path,
     *         when a configured mount cannot prefix a path, or when two configured
     *         roots would publish at one path
     * @throws NotFoundExceptionInterface When the config service is not registered
     * @throws FileWriterException When a version's files cannot be written
     * @throws Filesystem\Exception\ExceptionInterface When a previous extraction cannot be cleared
     * @throws Regex\Exception\ExceptionInterface When a tag cannot be matched, which the fixed
     *         pattern makes unreachable
     * @throws Type\Exception\ExceptionInterface When the captured groups are not the shape the
     *         pattern promises, which the fixed pattern makes unreachable
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): SiteConfig {
        $section = self::section($container->get('config'));

        return new SiteConfig(
            documentRoot: new DocumentRoot(
                basePath: $section['basePath'],
                pathType: $section['pathType'],
            ),
            roots       : $section['roots'],
            output      : $section['output'],
            projectName : $section['projectName'],
            projectSlug : $section['projectSlug'],
            versionLabel: $section['versionLabel'],
        );
    }
}
