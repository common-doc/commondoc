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

use LogicException as SplLogicException;

/**
 * This package was used in a way its contract forbids.
 *
 * The distinction from `RuntimeException` is worth keeping: a runtime failure
 * depends on the data, while this depends on the caller having asked for
 * something the type never promised, such as modifying an immutable page or
 * serving a build route. Both share the package interface, so a caller that only
 * wants "commondoc refused" catches either.
 *
 * @api
 */
final class LogicException extends SplLogicException implements ExceptionInterface {}
