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
 * One repository an organization search found.
 *
 * @api
 */
final readonly class Candidate
{
    /**
     * @param non-empty-string $name The repository's own name, which is also its checkout directory
     * @param non-empty-string $url  The URL it is cloned from
     */
    public function __construct(
        public string $name,
        public string $url,
    ) {}
}
