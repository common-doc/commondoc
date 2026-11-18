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
 * Every list is keyed by the local path, which is the same key a route name uses
 * and the same string a markdown link contains. One key space means the lists
 * can be checked against each other: a path that was found either appears as
 * written or appears as failed, and never as both.
 *
 * Assets share that key space and share both outcomes. A build publishes both
 * kinds of file, so a failure that named only documents would leave an operator
 * with a broken site and no way to find the file that caused it. The two are
 * still distinguishable, because `input` holds documents and `asset` holds
 * everything else; `output` and `failed` hold either kind.
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
    public array $asset;

    /** @var list<non-empty-string> */
    public array $output;

    /** @var array<non-empty-string, non-empty-string> */
    public array $failed;

    /**
     * @param list<non-empty-string>                    $input  Documents that were found
     * @param list<non-empty-string>                    $asset  Assets that were found
     * @param list<non-empty-string>                    $output Paths that were written, of either kind
     * @param array<non-empty-string, non-empty-string> $failed Local path to the reason it was not written
     *
     * @throws InvalidArgumentException When the lists disagree with each other
     */
    public function __construct(
        array $input,
        array $asset,
        array $output,
        array $failed,
    ) {
        $this->input  = self::ordered($input);
        $this->asset  = self::ordered($asset);
        $this->output = self::ordered($output);
        $this->failed = self::sortedByPath($failed);

        $this->requireEveryDiscoveredPathToBeAccountedFor();
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
     * Whether the build wrote every path it discovered.
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
     * The reason a path was not written, when it was not.
     */
    public function reasonFor(string $localPath): ?string
    {
        return $this->failed[$localPath] ?? null;
    }

    /**
     * The number of paths that were written, of either kind.
     *
     * @return non-negative-int
     */
    public function written(): int
    {
        return count($this->output);
    }

    /**
     * Every discovered path is either written or failed, and nothing is both.
     *
     * This is the invariant that makes the record trustworthy. A record that
     * silently dropped a path would report success for a site missing a file,
     * which is worse than reporting nothing at all.
     *
     * An asset cannot be written as a document and a document cannot be written
     * as an asset, so the two discovered lists must also be disjoint. Without
     * that check a path in both would be counted twice and would hide a
     * partition that had stopped partitioning.
     *
     * @throws InvalidArgumentException When the lists do not reconcile
     */
    private function requireEveryDiscoveredPathToBeAccountedFor(): void
    {
        $both = array_intersect_key($this->failed, array_flip($this->output));

        if ([] !== $both) {
            throw new InvalidArgumentException(sprintf(
                'The paths "%s" are recorded as both written and failed.',
                implode('", "', array_keys($both)),
            ));
        }

        $overlap = array_intersect_key(array_flip($this->input), array_flip($this->asset));

        if ([] !== $overlap) {
            throw new InvalidArgumentException(sprintf(
                'The paths "%s" are recorded as both a document and an asset.',
                implode('", "', array_keys($overlap)),
            ));
        }

        $discovered = [...$this->input, ...$this->asset];
        $accounted  = [...$this->output, ...array_keys($this->failed)];

        sort($discovered, SORT_STRING);
        sort($accounted, SORT_STRING);

        if ($accounted === $discovered) {
            return;
        }

        $unaccounted = array_keys(array_diff_key(array_flip($discovered), array_flip($accounted)));

        if ([] !== $unaccounted) {
            throw new InvalidArgumentException(sprintf(
                'The record accounts for %d paths while %d were discovered; "%s" is unaccounted for.',
                count($accounted),
                count($discovered),
                implode('", "', $unaccounted),
            ));
        }

        $extra = array_keys(array_diff_key(array_flip($accounted), array_flip($discovered)));

        throw new InvalidArgumentException(sprintf(
            'The record accounts for %d paths while %d were discovered; "%s" was never discovered.',
            count($accounted),
            count($discovered),
            implode('", "', $extra),
        ));
    }
}
