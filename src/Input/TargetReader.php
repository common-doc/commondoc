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

use function is_array;
use function is_string;

/**
 * The one place that answers what a configured `target` names.
 *
 * A site states its target once for every entry that states none, an entry states its own,
 * and an organization's entry states the answer for every repository it finds. All three are
 * the same value with the same meaning, so all three are read by this class: when two readers
 * decided it separately, one of them accepted a list and the other did not, and the same word
 * behaved two ways depending on where it was written.
 *
 * A list rather than one directory because a repository's layout changes between releases.
 * A tag that kept its documentation in `docs/v1` and a tag that keeps it in `docs/2.0` are
 * the same site, read from whichever directory that tag actually holds.
 *
 * Anything that is neither a non-empty string nor a list of them yields no candidates, so a
 * caller's own default applies rather than a refusal for a value it may not have meant.
 *
 * @internal
 */
final readonly class TargetReader
{
    /**
     * @param mixed $value Deliberately untyped: configuration is
     *
     * @return list<Target>
     *
     * @throws \CommonDoc\Exception\InvalidArgumentException When a candidate cannot name a directory
     */
    public function read(mixed $value): array
    {
        $targets = [];

        /** @var mixed $candidate Deliberately untyped: the check below decides what it is */
        foreach (is_array($value) ? $value : [$value] as $candidate) {
            if (! (is_string($candidate) && '' !== $candidate)) {
                continue;
            }

            $targets[] = new Target($candidate);
        }

        return $targets;
    }
}
