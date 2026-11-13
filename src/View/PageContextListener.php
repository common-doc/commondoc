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

use Dflydev\DotAccessData\Exception\DataException;
use Dflydev\DotAccessData\Exception\InvalidPathException;
use League\CommonMark\Event\DocumentPreRenderEvent;

/**
 * Carries the current page from the conversion caller to the document.
 *
 * Writing it into the document's data rather than into this listener's own
 * state keeps the page attached to the document it belongs to, which is what
 * the renderer reads, and it leaves a document that was built outside the
 * build loop with whatever page it already had.
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
    public function __invoke(DocumentPreRenderEvent $event): void
    {
        $page = $this->context->current();

        if (null === $page) {
            return;
        }

        $event->getDocument()->data->set('commondoc.page', $page);
    }
}
