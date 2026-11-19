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

namespace CommonDoc\Record;

use function ksort;
use function sort;

use const SORT_STRING;

/**
 * What one phase did, before the build is complete.
 *
 * A phase writes files and some of those writes fail, and the build reports both
 * once at the end. That means a phase cannot own the record: the record's
 * invariant is that every discovered path is accounted for, which is only true
 * after the last phase. So a phase contributes an outcome, and the record is
 * assembled from every outcome when the build is finished.
 *
 * This is deliberately not a partially valid `BuildRecord`. Two types with the
 * same fields but different guarantees would be confusing if either could be
 * passed where the other was expected, and the difference is exactly the
 * invariant: an outcome makes no claim about what was discovered.
 *
 * Accumulating is a merge rather than a mutation, so a phase hands its result
 * forward on the message the same way the scan does.
 *
 * @api
 */
final readonly class Outcome
{
    /**
     * @param list<non-empty-string>                    $output Paths the phase wrote
     * @param array<non-empty-string, non-empty-string> $failed Path to the reason it was not written
     */
    public function __construct(
        public array $output = [],
        public array $failed = [],
    ) {}

    /**
     * Nothing yet, which is what a build that has run no phases has done.
     */
    public static function none(): self
    {
        return new self();
    }

    /**
     * This outcome followed by another, so phases accumulate in the order they ran.
     *
     * The failures are keyed, so a later phase reporting a path an earlier one
     * already reported keeps the later reason. A path written by one phase and
     * failed by another is left as it is: that contradiction is what
     * `BuildRecord`'s invariant exists to catch, and resolving it here would hide
     * a defect behind a merge rule.
     */
    public function and(self $other): self
    {
        $output = [...$this->output, ...$other->output];
        $failed = [...$this->failed, ...$other->failed];

        sort($output, SORT_STRING);
        ksort($failed, SORT_STRING);

        return new self(
            output: $output,
            failed: $failed,
        );
    }
}
