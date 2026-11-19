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

namespace CommonDoc\View;

use CommonDoc\Exception\InvalidArgumentException;

use function sprintf;

/**
 * One heading in a document, as a link target.
 *
 * A document's sections are linkable without the renderer being asked, and the thing a
 * link needs is exactly this: the text to show and the fragment that reaches it. It is a
 * value rather than the heading node, because a consumer building navigation should not
 * have to hold a CommonMark node to name a section, and because the node is mutable and
 * stops existing when the build does.
 *
 * The fragment is read from the heading's own id rather than translated again here. The
 * id is what the renderer emits, so a second translation would be a second copy of the
 * rule and the copies would drift the first time the slug normalizer changed. `#fragment`
 * therefore reaches the heading it was taken from, by construction.
 *
 * @api
 */
final readonly class Heading
{
    /**
     * @param int    $level    The heading level, 1 to 6
     * @param string $label    The heading's text, as it reads on the page
     * @param string $fragment The id the heading carries, without the `#`
     *
     * @throws InvalidArgumentException When the level is outside 1 to 6, or the fragment is
     *                                  empty
     */
    public function __construct(
        public int $level,
        public string $label,
        public string $fragment,
    ) {
        if ($level < 1 || $level > 6) {
            throw new InvalidArgumentException(sprintf(
                'A heading level is 1 to 6, and %d was given for "%s".',
                $level,
                $label,
            ));
        }

        if ('' === $fragment) {
            throw new InvalidArgumentException(sprintf(
                'The heading "%s" carries no id, so nothing can link to it. A heading reaches a link target only when an extension assigns its id.',
                $label,
            ));
        }
    }
}
