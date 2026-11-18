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
 * The non-Markdown files beneath a source root.
 *
 * Images and stylesheets are published alongside the documents that reference
 * them, and a link to an image needs the same translation a link to a document
 * does. Without a list of assets those references have nothing to resolve
 * against, so the translation would either skip them or invent a path.
 *
 * The scan is the Markdown scan with a different filter, which is why these are
 * two types rather than one type with a mode.
 *
 * @api
 */
final readonly class Asset
{
    /**
     * Every asset beneath the root, ordered so two builds agree.
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
