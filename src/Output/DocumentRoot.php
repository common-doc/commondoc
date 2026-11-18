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

use InvalidArgumentException;
use LogicException;

use function sprintf;

/**
 * A local source root mounted at a URL prefix.
 *
 * The same relationship a web server has with a document root: a directory on
 * disk serves every file beneath it at exactly one address, and the address is
 * the file's path with the root stripped and the suffix mapped. Nothing else is
 * involved, and no host name is needed, because an address that begins with a
 * slash is complete without one.
 *
 * This is the single translation. The write pass asks it where a document's file
 * goes, and the render pass asks it what address a link target has. Asking one
 * type twice is what keeps a file and its address from drifting apart.
 *
 * A local root is never named only by its own path: a site published beneath a
 * prefix, such as a repository subpath, has an empty local root relative to the
 * prefix it is mounted at. The prefix is configuration, not a constant.
 *
 * @api
 */
final readonly class DocumentRoot
{
    /**
     * @param non-empty-string $basePath The prefix this root is mounted at, such as `/` or `/repo/`
     *
     * @throws InvalidArgumentException When the base path cannot prefix an address
     * @throws LogicException           Until construction is implemented
     */
    public function __construct(
        public string $basePath,
        public Style $style = Style::Directory,
    ) {
        throw new LogicException(sprintf('%s::__construct() is not implemented.', self::class));
    }

    /**
     * The file a local path is written to, relative to the output directory.
     *
     * @throws InvalidArgumentException When the local path does not name a document
     * @throws LogicException           Until the translation is implemented
     */
    public function file(string $localPath): string
    {
        throw new LogicException(sprintf(
            '%s::file() is not implemented for "%s".',
            self::class,
            $localPath,
        ));
    }

    /**
     * The address a local path is published at, such as `/guide/setup/`.
     *
     * @throws InvalidArgumentException When the local path does not name a document
     * @throws LogicException           Until the translation is implemented
     */
    public function published(string $localPath): string
    {
        throw new LogicException(sprintf(
            '%s::published() is not implemented for "%s".',
            self::class,
            $localPath,
        ));
    }
}
