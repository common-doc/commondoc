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

use CommonDoc\DocumentData;
use Dflydev\DotAccessData\Exception\DataException;
use Dflydev\DotAccessData\Exception\InvalidPathException;
use League\CommonMark\Event\DocumentParsedEvent;

/**
 * Carries the current page from the conversion caller to the document.
 *
 * Writing it into the document's data rather than into this listener's own
 * state keeps the page attached to the document it belongs to, which is what
 * the renderer reads, and it leaves a document that was built outside the
 * build loop with whatever page it already had.
 *
 * It runs on the parsed event rather than before rendering because the page is
 * also where a document's own path lives, and a link rewriter has to know which
 * document it is rewriting for before it can resolve a relative target. Data
 * written at parse time is still on the document when it renders, so the
 * renderer reads the same value one event later than it used to. The caller
 * sets the page before converting, so the value exists by the time either event
 * fires.
 */
final readonly class PageContextListener
{
    public function __construct(
        private PageContext $context,
    ) {}

    /**
     * @throws DataException when the document data cannot be written
     * @throws InvalidPathException when the data key cannot be resolved
     */
    public function __invoke(DocumentParsedEvent $event): void
    {
        $page = $this->context->current();

        if (null === $page) {
            return;
        }

        $event->getDocument()->data->set(DocumentData::Page->value, $page);
    }
}
