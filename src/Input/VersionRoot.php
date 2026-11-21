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

namespace CommonDoc\Input;

use CommonDoc\Exception\InvalidArgumentException;
use Psl\Filesystem;
use Psl\Regex;
use Psl\Type;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;

use function basename;
use function sprintf;
use function str_replace;

/**
 * The roots a repository's released versions make, one per version, so every version of
 * a component is built and published beside the others.
 *
 * Each version's documentation is read from its tag and written to the workspace, then
 * mounted at `/{component}/v{major}/`. The scan and the write pass read a root and know
 * nothing of tags: a versioned site is the same path space with more roots in it.
 *
 * A tag that holds nothing at the target is skipped rather than refused, because a
 * repository's early tags predate its documentation and an old release should not stop
 * the versions that have some. A repository with no GitHub origin has no component
 * identity to mount under, so it yields no roots.
 *
 * @internal
 */
final readonly class VersionRoot
{
    /** The placeholder in a target that each tag replaces with its own major version. */
    private const string MAJOR = '{major}';

    public function __construct(
        private Git $git = new Git(),
        private VersionReader $versions = new VersionReader(),
        private TagTree $trees = new TagTree(),
    ) {}

    /**
     * @param string $repository The checkout whose tags are read
     * @param Target $target     The documentation directory within each tag
     * @param string $workspace  Where each version's tree is written
     *
     * @return list<Root>
     *
     * @throws InvalidArgumentException When a mount cannot prefix a path
     * @throws FileWriterException When a version's files cannot be written
     * @throws Filesystem\Exception\ExceptionInterface When a previous extraction cannot be cleared
     * @throws Regex\Exception\ExceptionInterface When a tag cannot be matched, which the fixed
     *         pattern makes unreachable
     * @throws Type\Exception\ExceptionInterface When the captured groups are not the shape the
     *         pattern promises, which the fixed pattern makes unreachable
     */
    public function of(string $repository, Target $target, string $workspace): array
    {
        $remote = $this->git->ask($repository, ['remote', 'get-url', 'origin']);
        $origin = null === $remote ? null : Source::fromRemote($remote, ref: 'HEAD');

        if (null === $origin) {
            return [];
        }

        $component = new Component(basename($origin->url));
        $segment   = $component->segment;
        $roots     = [];

        foreach ($this->versions->read($repository) as $version) {
            // A repository that keeps one directory per major states `docs/v{major}` once,
            // and each tag is read from its own.
            $own       = new Target(str_replace(self::MAJOR, (string) $version->major, $target->location));
            $directory = sprintf('%s/%s/v%d', $workspace, $segment, $version->major);
            $written   = $this->trees->extract(
                directory  : $repository,
                ref        : $version->tag,
                path       : $own->location,
                destination: $directory,
            );

            if (0 === $written) {
                continue;
            }

            $roots[] = new Root(
                path   : $own->at($directory),
                mount  : sprintf('/%s/v%d/', $segment, $version->major),
                source : new Source(
                    url   : $origin->url,
                    ref   : $version->tag,
                    prefix: $own->location,
                ),
                release: new Release($component, $version),
            );
        }

        return $roots;
    }
}
