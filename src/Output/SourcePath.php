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

namespace CommonDoc\Output;

use CommonDoc\Exception\RuntimeException;

use function array_pop;
use function dirname;
use function explode;
use function implode;
use function sprintf;
use function str_starts_with;

/**
 * Resolves a target written in a document to the path it names.
 *
 * Both spaces this package works in use the same rule, so the rule lives here rather
 * than in either caller. A site path is a source path with its mount applied, and a
 * link is written the way a file system reads it, so `../images/d.png` has to mean the
 * same thing whether it is being resolved in site space or in repository space.
 *
 * An absolute path is relative to the root of whichever space it is resolved in,
 * because a published site begins at its root and that is the only absolute space a
 * reader knows about.
 *
 * @internal
 */
final class SourcePath
{
    private const string SEPARATOR = '/';

    private const string CURRENT = '.';

    private const string PARENT = '..';

    /**
     * The path with empty, current and parent segments resolved.
     *
     * A path that leaves the root is refused rather than clamped: it names a file that
     * cannot be published, and a clamp would silently point at something else.
     *
     * @throws RuntimeException when a parent segment would climb above the root
     */
    public static function normalize(string $path): string
    {
        $segments = [];

        foreach (explode(self::SEPARATOR, $path) as $segment) {
            if ('' === $segment || self::CURRENT === $segment) {
                continue;
            }

            if (self::PARENT !== $segment) {
                $segments[] = $segment;

                continue;
            }

            if ([] === $segments) {
                throw new RuntimeException(sprintf(
                    'The path "%s" leaves the source root.',
                    $path,
                ));
            }

            array_pop($segments);
        }

        return implode(self::SEPARATOR, $segments);
    }

    /**
     * The path a target names, as seen from the document holding it.
     *
     * @param string $path The target exactly as the document wrote it
     * @param string $from The local path of the document holding it
     *
     * @throws RuntimeException when the target climbs above the root
     */
    public static function resolve(string $path, string $from): string
    {
        $base = str_starts_with($path, self::SEPARATOR) ? '' : dirname($from);

        return self::normalize($base . self::SEPARATOR . $path);
    }
}
