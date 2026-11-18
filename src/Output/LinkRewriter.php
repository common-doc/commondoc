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

use function count;
use function sprintf;

/**
 * Rewrites document links and image references to published paths.
 *
 * A Markdown source refers to its neighbours the way a file system does, so a
 * link that is correct in the repository is wrong on a published site. This runs
 * on the parsed tree rather than on finished HTML, because a parsed link has a
 * known target while a string replacement would also rewrite prose that happens
 * to look like a path, and attribute values it was never meant to touch.
 *
 * It reads the same translation the write pass uses, so a path in a link and the
 * file that serves it are produced by one code path and cannot disagree.
 *
 * Two collaborators, each answering one question:
 *
 * - `DocumentRoot` says what a local path is published at. It is a pure
 *   function, so it needs no table and no cache to be asked once per link.
 * - The document list says whether a target exists. Recomputation can produce a
 *   path for anything; only the discovered set can say the target is real, and a
 *   link to a document that does not exist has to fail the build rather than
 *   publish a dead link.
 *
 * @api
 */
final readonly class LinkRewriter
{
    /**
     * @param list<non-empty-string> $documents Every document the scan discovered
     */
    public function __construct(
        private DocumentRoot $root,
        private array $documents,
    ) {}

    /**
     * @throws LogicException Until link rewriting is implemented
     */
    // @mago-expect analysis:unused-parameter - the event is the listener contract; the
    // tree walk arrives in the next commit and the contract must not be reshaped for it.
    public function __invoke(DocumentParsedEvent $event): void
    {
        throw new LogicException(sprintf(
            '%s::__invoke() is not implemented; %d documents under "%s" are published with their links unchanged.',
            self::class,
            count($this->documents),
            $this->root->basePath,
        ));
    }
}
