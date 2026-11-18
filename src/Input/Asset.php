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

namespace CommonDoc\Input;

use function array_diff;
use function array_values;

/**
 * The files in a list of paths that are not documents.
 *
 * Images and stylesheets are published alongside the documents that reference
 * them, and a link to an image needs the same translation a link to a document
 * does. Without a list of assets those references have nothing to resolve
 * against, so the translation would either skip them or invent a path.
 *
 * An asset is defined by exclusion: it is a path the document filter did not
 * claim. Restating the document test here as its negation would be a second copy
 * of one rule, and two copies drift. The failure is specific rather than
 * theoretical: were the document test widened to accept another suffix, a
 * hand-written negation would keep calling those files assets, and one file
 * would be published twice, once rendered and once copied.
 *
 * As a difference the two lists are total and disjoint by construction, and
 * neither property depends on anyone remembering to keep the two tests in step.
 *
 * It takes the paths rather than a root so that a build walks the tree once and
 * filters the result per type. Walking per type would cost one traversal each,
 * and traversal is the expensive part; a suffix test is a string comparison.
 *
 * A file that happens to be unreadable is left in the list on purpose: it will
 * fail loudly when it is copied, which is more useful than vanishing from the
 * build with a broken link as the only symptom.
 *
 * @api
 */
final readonly class Asset
{
    /**
     * Every path the document filter did not claim, in the order it arrived.
     *
     * Order is inherited rather than imposed, for the same reason `Markdown`
     * inherits it: the traversal already sorted by byte value.
     *
     * @param list<non-empty-string> $paths     Every file beneath the source root
     * @param list<non-empty-string> $documents The paths that are documents
     *
     * @return list<non-empty-string>
     */
    public function scan(array $paths, array $documents): array
    {
        return array_values(array_diff($paths, $documents));
    }
}
