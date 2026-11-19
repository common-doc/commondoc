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

namespace CommonDoc\Http;

use CommonDoc\Exception\HttpException;
use InvalidArgumentException;
use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Assembles a PSR-7 response from what a transfer produced.
 *
 * Separated from the transport because it is the half that is pure: a status, a header map and a
 * body go in, a response comes out, and nothing here touches the network. The transport keeps the
 * cURL handle, which is the part a test cannot supply.
 *
 * The message implementation and the stream are both free to refuse what a server sent, and a
 * server-controlled value must not escape as an arbitrary exception type. So both are caught and
 * re-raised as this package's own exception, which is also what PSR-18 requires of a client.
 *
 * @internal
 */
final readonly class ResponseBuilder
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory = new ResponseFactory(),
    ) {}

    /**
     * @param array<string, list<string>> $headers
     *
     * @throws HttpException when the response message cannot be assembled
     */
    public function build(
        int $status,
        array $headers,
        string $body,
    ): ResponseInterface {
        try {
            $response = $this->responseFactory->createResponse($status);

            foreach ($headers as $name => $values) {
                $response = $response->withAddedHeader($name, $values);
            }

            $stream = $response->getBody();
            $stream->write($body);
            $stream->rewind();
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new HttpException('The HTTP response could not be built.', previous: $exception);
        }

        return $response;
    }
}
