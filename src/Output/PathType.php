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
 * Which kind of path a document is published at.
 *
 * Both cases name a path and they differ in one respect: whether that path is a
 * directory path or a file path. There is no third option and no ordering
 * between them, so a closed set is the honest shape and an enum is what carries
 * it.
 *
 * The type is a property of the site rather than of a single write, because
 * every internal link must agree with it. Two types in one build would produce
 * links pointing at paths nothing serves.
 *
 * The output file follows from the published path: a directory path is served
 * by an `index.html` inside it, and a file path is itself the file. So this
 * decides the published path and the file is derived, which is why the name describes
 * the path rather than the layout.
 *
 * @api
 */
enum PathType: string
{
    /** `guide/setup.md` is published at `/guide/setup/`, served by `guide/setup/index.html`. */
    case Directory = 'directory';

    /** `guide/setup.md` is published at `/guide/setup.html`, which is the file. */
    case File = 'file';
}
