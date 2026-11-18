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

/**
 * How a document path becomes a published path.
 *
 * A published path is what a browser asks for. The style decides whether a
 * document is served from its own URL or from a directory address, and both are
 * legitimate: one mirrors the source tree exactly, the other produces the
 * cleaner addresses a static host serves natively.
 *
 * The style is a property of the site rather than of a single write, because
 * every internal link must agree with it. Two styles in one build would produce
 * links that point at addresses nothing serves.
 *
 * @api
 */
enum Style: string
{
    /** `guide/setup.md` is published at `/guide/setup/` and served from `index.html`. */
    case Directory = 'directory';

    /** `guide/setup.md` is published at `/guide/setup.html`. */
    case Plain = 'plain';
}
