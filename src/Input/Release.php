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
 * Which component and which released version a root holds.
 *
 * A root made from a tag carries this, so the site can list every version of a component
 * and say which is the latest without reading a mount path back apart.
 *
 * @api
 */
final readonly class Release
{
    public function __construct(
        public Component $component,
        public Version $version,
    ) {}
}
