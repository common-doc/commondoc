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

namespace CommonDoc\Exception;

use Throwable;

/**
 * Marks every failure this package raises of its own accord.
 *
 * A caller that wants "the build refused something" catches this, rather than a
 * union of SPL classes that a collaborator could also throw. That distinction is
 * the whole point: a document that fails to parse is a CommonMark problem, while
 * a local path that is not a document is this package's problem, and the two are
 * worth telling apart.
 *
 * It extends `Throwable` deliberately. Several packages in the fleet declare a
 * marker interface without extending it, which looks the same at a glance but
 * cannot be used in a catch type, because nothing then guarantees the value is
 * throwable at all.
 *
 * `Error` and its children are outside this hierarchy on purpose. A `TypeError`
 * is a defect in this program, not a refusal, and it must not be presented to an
 * operator as a problem with their documentation.
 *
 * @api
 */
interface ExceptionInterface extends Throwable {}
