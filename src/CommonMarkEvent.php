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

namespace CommonDoc;

use League\CommonMark\Event\DocumentParsedEvent;

/**
 * Every CommonMark event this package acts on.
 *
 * `Environment::addEventListener()` takes an identifier, and this package passes
 * one in two places, so the question "what do we hook into" has two answers in two
 * factories and no single place that states it. This is that place. Reading it
 * tells you the whole hook surface at once, and a new hook cannot be added without
 * appearing here.
 *
 * The value has to be the event's class name. `Environment::getListenersForEvent()`
 * matches with `is_a($event, $listenerData->getEvent())`, so anything that is not a
 * real class name registers a listener that never fires, and it fails silently.
 *
 * The case value is `DocumentParsedEvent::class`, so the identifier is a reference
 * to the class rather than a written-out string: a rename or a move in the library
 * breaks here instead of silently unhooking every listener.
 *
 * That also means it carries one suppression, and the rule it silences is a
 * false positive rather than a design compromise. PHP accepts `SomeClass::class` as
 * the value of a backed case: `enum E: string { case A = stdClass::class; }` yields
 * `'stdClass'`, verified on this PHP version. The analyzer infers
 * `class-string<DocumentParsedEvent>` for that expression and rejects it as not a
 * `string`, which is wrong in substance because a `class-string` is a `string`.
 *
 * The alternative encodings were measured rather than guessed. Writing the class
 * name as a literal satisfies the analyzer and trips `lint`'s
 * `no-literal-namespace-string`. Concatenating an empty string satisfies the linter
 * but widens the value to `string`, which `addEventListener()` then rejects because
 * it takes a `class-string`. Keeping `::class` with one expectation satisfies both,
 * and keeps the property that matters: the identifier is a reference to the class,
 * so a rename or a move in the library breaks here rather than silently unhooking
 * every listener.
 *
 * Only the events we act on are cases. The set is not CommonMark's four events, it
 * is ours, and `DocumentPreParsedEvent`, `DocumentPreRenderEvent` and
 * `DocumentRenderedEvent` are absent because nothing here listens for them. When
 * something does, it gains a case and a row in `CommonMarkEventTest`.
 *
 * @api
 */
enum CommonMarkEvent: string
{
    /**
     * Fires once per document, after the tree is built and before anything renders.
     *
     * This is where a document gains its page and where links are resolved, which
     * is one event rather than two because a link rewriter has to know which
     * document it is rewriting for before it can resolve a relative target.
     */
    // @mago-expect analysis:invalid-enum-case-value
    case DocumentParsed = DocumentParsedEvent::class;
}
