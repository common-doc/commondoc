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

namespace CommonDoc\Record;

use CommonDoc\Exception\ExceptionInterface;
use League\CommonMark\Exception\CommonMarkException;
use League\Config\Exception\ConfigurationExceptionInterface;
use Psl\Exception\ExceptionInterface as PslExceptionInterface;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;

/**
 * The set of failures a build records, and the reason it gives for each.
 *
 * The union is written once here rather than at every catch, because the two
 * places that record a failure have to agree about what is recordable. A second
 * copy would drift the first time a member was added, and the drift would be
 * silent: one pass would record a failure the other let propagate.
 *
 * Every member is a failure of the file being processed. `Error`, `TypeError` and
 * every other engine failure stay outside the set on purpose, so a defect in the
 * builder propagates instead of being presented as a problem with the
 * documentation, which would send an operator after a file that is fine.
 *
 * This package's own refusals are one member through `ExceptionInterface`, which
 * is what that interface exists for: without it each of its implementers would
 * have to be named, and one added later would silently stop being recorded.
 *
 * @api
 */
final readonly class Reason
{
    /**
     * A reason a path was not written, never empty.
     *
     * The parameter repeats the caught union rather than naming `Throwable`.
     * `BuildRecord` requires a non-empty reason, so a failure with no message is
     * described by its own class rather than by an empty string that would read as
     * though nothing went wrong.
     *
     * @return non-empty-string
     */
    public static function of(
        CommonMarkException|ConfigurationExceptionInterface|ExceptionInterface|FileWriterException|PslExceptionInterface $failure,
    ): string {
        $message = $failure->getMessage();

        if ('' === $message) {
            return $failure::class;
        }

        return $message;
    }
}
