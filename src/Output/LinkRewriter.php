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

namespace CommonDoc\Output;

use League\CommonMark\Event\DocumentParsedEvent;
use LogicException;

use function sprintf;

/**
 * Rewrites document links and image references to published addresses.
 *
 * A Markdown source refers to its neighbours the way a file system does, so a
 * link that is correct in the repository is wrong on a published site. This runs
 * on the parsed tree rather than on finished HTML, because a parsed link has a
 * known target while a string replacement would also rewrite prose that happens
 * to look like a path, and attribute values it was never meant to touch.
 *
 * It reads the same translation the write pass uses, so an address in a link and
 * the file that serves it are produced by one code path and cannot disagree.
 *
 * @api
 */
final readonly class LinkRewriter
{
    public function __construct(
        private DocumentRoot $root,
    ) {}

    /**
     * @throws LogicException Until link rewriting is implemented
     */
    // @mago-expect analysis:unused-parameter - the event is the listener contract; the
    // tree walk arrives in the next commit and the contract must not be reshaped for it.
    public function __invoke(DocumentParsedEvent $event): void
    {
        throw new LogicException(sprintf(
            '%s::__invoke() is not implemented; links under "%s" are published unchanged.',
            self::class,
            $this->root->basePath,
        ));
    }
}
