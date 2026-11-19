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

use InvalidArgumentException as SplInvalidArgumentException;

/**
 * A value this package was given cannot be used.
 *
 * It extends SPL's class rather than the package's shared interface alone, so a
 * caller that already catches `InvalidArgumentException` keeps working. That
 * matters for every existing call site: the change that introduced this type
 * needed no catch to be rewritten.
 *
 * @api
 */
final class InvalidArgumentException extends SplInvalidArgumentException implements ExceptionInterface {}
