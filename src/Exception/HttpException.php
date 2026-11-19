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

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Raised when the transport itself fails, as opposed to the server answering with an error status.
 *
 * PSR-18 requires `sendRequest()` to signal a transport failure by throwing a
 * `ClientExceptionInterface` rather than by returning an error response, so this is one.
 *
 * It also carries this package's `ExceptionInterface`, which is what makes an unreachable
 * repository a refusal the console renders and exits `INVALID` on, rather than an exception that
 * tears down the session. It extends SPL's `RuntimeException` directly because this package's own
 * `RuntimeException` is final and cannot be extended.
 *
 * @api
 */
final class HttpException extends RuntimeException implements ClientExceptionInterface, ExceptionInterface
{
    public function __construct(
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, code: 0, previous: $previous);
    }
}
