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

/**
 * One configured entry read as the roots it describes.
 *
 * Every entry yields zero or more roots, which is why the answer is a list even for the entry
 * that names a single directory: that is what lets one list of entries hold a repository, a
 * repository's every released version, and an organization's every repository without the
 * caller branching on which kind it is holding.
 *
 * A reader answers `null` for an entry it does not recognise, so the next reader is asked.
 * An empty list is a different answer and means the entry was recognised and describes no
 * root, which is final for that entry.
 *
 * @internal
 */
interface EntryReaderInterface
{
    /**
     * @param mixed        $entry     Deliberately untyped: configuration is
     * @param list<Target> $fallbacks The site's targets, used by an entry that states none
     *
     * @return ?list<Root> Null when the entry is not this reader's kind
     */
    public function read(mixed $entry, array $fallbacks): ?array;
}
