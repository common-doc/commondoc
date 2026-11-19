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

/**
 * The keys this package owns inside a CommonMark document's data bag.
 *
 * A document's data bag is CommonMark's vocabulary for "anything an extension
 * needs to carry", and it is reached by string key at every call site. Three
 * classes do exactly that: `PageContextListener` writes the page, and
 * `LinkRewriter` and `DocumentViewRenderer` both read it. A literal repeated three
 * times is a literal that can drift, and the drift is silent: a listener writing
 * one key while the renderer reads another produces a page with no identity rather
 * than an error.
 *
 * Every case is prefixed with `commondoc.` so the bag cannot collide with a key
 * another extension chose. That prefix is part of the value rather than something
 * a caller adds, because a caller that forgot it would write into another
 * package's key space and no type would notice.
 *
 * This is a different value space from `CommonMarkEvent`, which is why they are not
 * one enum. An event identifier has to be the event's class name, because
 * `Environment::getListenersForEvent()` matches it with `is_a()`; a bag key is an
 * arbitrary string registered nowhere and meaningful only to the call sites that
 * agree on it. One enum holding both would be a bag of unrelated literals.
 *
 * @api
 */
enum DocumentData: string
{
    /**
     * The page a document belongs to, written before a link is resolved.
     *
     * `PageContextListener` sets it at parse time so that a link rewriter knows
     * which document it is rewriting for, and the value is still on the document
     * when it renders, so the renderer reads the same one an event later.
     */
    case Page = 'commondoc.page';
}
