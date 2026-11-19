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

use RuntimeException as SplRuntimeException;

/**
 * The work could not be carried out, given otherwise valid input.
 *
 * Used where the refusal depends on the data rather than on how the type was
 * called: a link whose target the scan never found, for instance. The condition
 * is discoverable only by doing the work, which is what separates it from
 * `LogicException`.
 *
 * It extends SPL's class so a caller catching `RuntimeException` keeps working.
 *
 * @api
 */
final class RuntimeException extends SplRuntimeException implements ExceptionInterface {}
