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

use InvalidArgumentException;
use LogicException;

use function sprintf;

/**
 * What one build discovered, wrote and failed to write.
 *
 * Three facts rather than one, because the operator needs all three to act: what
 * was found, what exists now, and which of the found documents did not make it.
 * Reporting a bare list of outputs cannot distinguish a document that was never
 * found from one that failed, and that difference is the whole diagnostic.
 *
 * A failure carries its reason against the local path it belongs to, so the
 * report names the document rather than only the fact that something went wrong.
 *
 * This is what the command returns across the bus. It is not a log: the log is a
 * durable record for later review, while this is what the operator reads now.
 *
 * @api
 */
final readonly class BuildRecord
{
    /**
     * @param list<non-empty-string>                    $input  Documents that were found, ordered by local path
     * @param list<non-empty-string>                    $output Files that were written, ordered by local path
     * @param array<non-empty-string, non-empty-string> $failed Local path to the reason it was not written
     *
     * @throws InvalidArgumentException When the lists disagree with each other
     * @throws LogicException           Until construction is implemented
     */
    public function __construct(
        public array $input,
        public array $output,
        public array $failed,
    ) {
        throw new LogicException(sprintf('%s::__construct() is not implemented.', self::class));
    }
}
