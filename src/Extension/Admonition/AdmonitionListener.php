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

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use Psl\Regex;
use Psl\Type;

/**
 * Turns a blockquote that opens with `[!NOTE]` into an admonition.
 *
 * ```markdown
 * > [!WARNING]
 * > Back up the database first.
 * ```
 *
 * This is GitHub's alert syntax, so the same file reads as a blockquote there and as a
 * callout here. The marker has to be alone on the first line: `> [!NOTE] some text` is an
 * ordinary blockquote, as it is on GitHub, because a marker followed by text on its own
 * line is much more likely to be a quotation of something else than a callout.
 *
 * It works on the parsed tree rather than as a block parser, so a blockquote's own parsing,
 * including nesting, lists and code inside it, is exactly CommonMark's, and only the node
 * that holds it changes.
 *
 * @api
 */
final readonly class AdmonitionListener
{
    /**
     * @throws Regex\Exception\ExceptionInterface When a fixed pattern cannot be applied, which it cannot
     * @throws Type\Exception\ExceptionInterface When a match is not the shape the pattern promises
     */
    public function __invoke(DocumentParsedEvent $event): void
    {
        $quotes = [];
        $walker = $event->getDocument()->walker();

        while (null !== ($step = $walker->next())) {
            $node = $step->getNode();

            if ($step->isEntering() && $node instanceof BlockQuote) {
                $quotes[] = $node;
            }
        }

        // Collected first, because replacing a node while walking would disturb the walk.
        foreach ($quotes as $quote) {
            $paragraph = $quote->firstChild();
            $marker    = $paragraph instanceof Paragraph ? AdmonitionMarker::in($paragraph) : null;

            if (null === $marker || ! $paragraph instanceof Paragraph) {
                continue;
            }

            $marker->remove($paragraph);

            $admonition = new Admonition($marker->kind);

            foreach ([...$quote->children()] as $child) {
                $admonition->appendChild($child);
            }

            $quote->replaceWith($admonition);
        }
    }
}
