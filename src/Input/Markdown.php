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

use InvalidArgumentException;
use LogicException;

use function sprintf;

/**
 * The Markdown documents beneath a source root.
 *
 * It answers one question: which documents exist. It knows nothing about output
 * paths or published paths, so the translation stays in one place rather than
 * being spread across every type that happens to need a file list.
 *
 * Paths are returned relative to the root with forward slashes, so a value is
 * safe to record, compare and use as a route name on any platform.
 *
 * @api
 */
final readonly class Markdown
{
    /**
     * Every `.md` file beneath the root, ordered so two builds agree.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException When the root is not a readable directory
     * @throws LogicException           Until the scan is implemented
     */
    public function scan(string $root): array
    {
        throw new LogicException(sprintf('%s::scan() is not implemented for the root "%s".', self::class, $root));
    }
}
