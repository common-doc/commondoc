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

namespace CommonDoc\Extension\Admonition;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A callout block: a note, tip, warning and so on, holding whatever blocks it was written with.
 *
 * It replaces the blockquote it was parsed as, so its children are the blockquote's own,
 * minus the marker line.
 *
 * @api
 */
final class Admonition extends AbstractBlock
{
    public function __construct(
        public readonly AdmonitionKind $kind,
    ) {
        parent::__construct();
    }
}
