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

use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function count;
use function implode;
use function ksort;
use function sort;
use function sprintf;

use const SORT_STRING;

/**
 * What one build discovered, wrote and failed to write.
 *
 * All three lists are keyed by the local path, which is the same key the route
 * table uses and the same string a markdown link contains. One key space means
 * the three lists can be checked against each other: a document that was found
 * either appears as written or appears as failed, and never as both.
 *
 * An output file is deliberately not recorded here. The file a document is
 * written to is derived from its local path, so storing it would be a second
 * copy of a fact that can go stale, and it would put file paths and document
 * paths in one list where they could not be compared.
 *
 * This is what the command returns across the bus, and it is not a log. The
 * operator reads this now; a log is a durable record for later review.
 *
 * @api
 */
final readonly class BuildRecord
{
    /** @var list<non-empty-string> */
    public array $input;

    /** @var list<non-empty-string> */
    public array $output;

    /** @var array<non-empty-string, non-empty-string> */
    public array $failed;

    /**
     * @param list<non-empty-string>                    $input  Documents that were found
     * @param list<non-empty-string>                    $output Documents that were written
     * @param array<non-empty-string, non-empty-string> $failed Local path to the reason it was not written
     *
     * @throws InvalidArgumentException When the lists disagree with each other
     */
    public function __construct(
        array $input,
        array $output,
        array $failed,
    ) {
        $this->input  = self::ordered($input);
        $this->output = self::ordered($output);
        $this->failed = self::sortedByPath($failed);

        $this->requireEveryDiscoveredDocumentToBeAccountedFor();
    }

    /**
     * A list of document paths, ordered by byte value.
     *
     * @param list<non-empty-string> $paths
     *
     * @return list<non-empty-string>
     */
    private static function ordered(array $paths): array
    {
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * A keyed list of reasons, ordered by byte value of the path.
     *
     * @param array<non-empty-string, non-empty-string> $failed
     *
     * @return array<non-empty-string, non-empty-string>
     */
    private static function sortedByPath(array $failed): array
    {
        ksort($failed, SORT_STRING);

        return $failed;
    }

    /**
     * Whether the build wrote every document it found.
     */
    public function complete(): bool
    {
        return [] === $this->failed;
    }

    /**
     * The failure paths, ordered, so the command prints a stable order.
     *
     * @return list<non-empty-string>
     */
    public function failedPath(): array
    {
        return array_keys($this->failed);
    }

    /**
     * The reason a document was not written, when it was not.
     */
    public function reasonFor(string $localPath): ?string
    {
        return $this->failed[$localPath] ?? null;
    }

    /**
     * The number of documents that were written.
     *
     * @return non-negative-int
     */
    public function written(): int
    {
        return count($this->output);
    }

    /**
     * Everything discovered is either written or failed, and nothing is both.
     *
     * This is the invariant that makes the record trustworthy. A record that
     * silently dropped a document would report success for a site missing a
     * document, which is worse than reporting nothing at all.
     *
     * @throws InvalidArgumentException When the three lists do not reconcile
     */
    private function requireEveryDiscoveredDocumentToBeAccountedFor(): void
    {
        $both = array_intersect_key($this->failed, array_flip($this->output));

        if ([] !== $both) {
            throw new InvalidArgumentException(sprintf(
                'The documents "%s" are recorded as both written and failed.',
                implode('", "', array_keys($both)),
            ));
        }

        $accounted = [...$this->output, ...array_keys($this->failed)];

        sort($accounted, SORT_STRING);

        if ($accounted === $this->input) {
            return;
        }

        $unaccounted = array_diff_key(array_flip($this->input), array_flip($accounted));

        throw new InvalidArgumentException(sprintf(
            'The record accounts for %d documents while %d were discovered; "%s" is unaccounted for.',
            count($accounted),
            count($this->input),
            implode('", "', array_keys($unaccounted)),
        ));
    }
}
