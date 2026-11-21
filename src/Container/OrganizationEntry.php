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

namespace CommonDoc\Container;

use function is_array;
use function is_string;

/**
 * One `organization` entry of the site configuration, read for what it asks.
 *
 * Configuration is untyped, so the shape is decided here once and the reader that acts on
 * it receives strings and a list. An entry without an organization is not one of these.
 *
 * @internal
 */
final readonly class OrganizationEntry
{
    public const string PROPERTY = 'doc-target';

    /**
     * @param non-empty-string $organization The organization whose repositories are searched
     * @param non-empty-string $property     The custom property that must be true
     * @param list<string>     $exclude      Repositories that carry the property but are not components
     * @param mixed            $target       The `target` the entry states, passed on untouched
     */
    private function __construct(
        public string $organization,
        public string $property,
        public array $exclude,
        public mixed $target,
    ) {}

    /**
     * @param mixed $entry Deliberately untyped: configuration is
     */
    public static function from(mixed $entry): ?self
    {
        if (! is_array($entry)) {
            return null;
        }

        /** @var mixed $organization Deliberately untyped: the check below decides what it is */
        $organization = $entry['organization'] ?? null;

        if (! is_string($organization) || '' === $organization) {
            return null;
        }

        /** @var mixed $property Deliberately untyped: the check below decides what it is */
        $property = $entry['property'] ?? null;

        return new self(
            organization: $organization,
            property    : is_string($property) && '' !== $property ? $property : self::PROPERTY,
            exclude     : self::names($entry['exclude'] ?? null),
            target      : $entry['target'] ?? null,
        );
    }

    /**
     * @param mixed $value Deliberately untyped: configuration is
     *
     * @return list<string>
     */
    private static function names(mixed $value): array
    {
        $names = [];

        /** @var mixed $name Deliberately untyped: the check below decides what it is */
        foreach (is_array($value) ? $value : [] as $name) {
            if (! is_string($name)) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }
}
