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

use CommonDoc\Exception\RuntimeException;
use InvalidArgumentException as NativeInvalidArgumentException;
use Laminas\Diactoros\RequestFactory;
use Psl\Json;
use Psl\Type;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use SensitiveParameter;

use function count;
use function http_build_query;
use function sprintf;

/**
 * The repositories of an organization that carry a custom property set to true.
 *
 * Asked of GitHub's repository search with the `props.` qualifier, so one query answers it
 * and nothing is listed and filtered here. The whole of the discovery is this question; what
 * is then done with each repository is not this type's concern.
 *
 * An archived repository is left out, because it is not being maintained and its tags will
 * not change. A token is optional: without one the search is limited to public repositories
 * and a much lower rate, which is enough for an organization of this size.
 *
 * GitHub returns at most a thousand search results, so the walk stops there rather than
 * paging on for results that are never delivered.
 *
 * @api
 */
final readonly class OrganizationSearch
{
    private const string ENDPOINT = 'https://api.github.com/search/repositories';

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 10;

    private const int OK = 200;

    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requests = new RequestFactory(),
        #[SensitiveParameter]
        private string $token = '',
    ) {}

    /**
     * @param non-empty-string $organization The organization to search
     * @param non-empty-string $property     The custom property that must be true
     *
     * @return list<Candidate>
     *
     * @throws ClientExceptionInterface When the request cannot be sent
     * @throws NativeInvalidArgumentException When a request cannot be composed, which the fixed endpoint makes unreachable
     * @throws RuntimeException When GitHub answers with anything but success
     * @throws Json\Exception\DecodeException When the answer is not JSON
     * @throws Type\Exception\ExceptionInterface When the answer is not the shape the API documents
     */
    public function repositories(string $organization, string $property): array
    {
        $found = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $items = $this->page($organization, $property, $page);

            foreach ($items as $item) {
                if ($item['archived'] || '' === $item['name'] || '' === $item['clone_url']) {
                    continue;
                }

                $found[] = new Candidate($item['name'], $item['clone_url']);
            }

            if (self::PAGE_SIZE > count($items)) {
                break;
            }
        }

        return $found;
    }

    /**
     * @return list<array{name: string, clone_url: string, archived: bool}>
     *
     * @throws ClientExceptionInterface When the request cannot be sent
     * @throws NativeInvalidArgumentException When a request cannot be composed, which the fixed endpoint makes unreachable
     * @throws RuntimeException When GitHub answers with anything but success
     * @throws Json\Exception\DecodeException When the answer is not JSON
     * @throws Type\Exception\ExceptionInterface When the answer is not the shape the API documents
     */
    private function page(string $organization, string $property, int $page): array
    {
        $request = $this->requests
            ->createRequest(
                method: 'GET',
                uri   : self::ENDPOINT . '?'
                    . http_build_query([
                        'q'        => "org:{$organization} props.{$property}:true",
                        'per_page' => self::PAGE_SIZE,
                        'page'     => $page,
                    ]),
            )
            ->withHeader(
                name : 'Accept',
                value: 'application/vnd.github+json',
            )
            ->withHeader(
                name : 'X-GitHub-Api-Version',
                value: '2022-11-28',
            );

        if ('' !== $this->token) {
            $request = $request->withHeader(
                name : 'Authorization',
                value: "Bearer {$this->token}",
            );
        }

        $response = $this->client->sendRequest($request);

        if (self::OK !== $response->getStatusCode()) {
            throw new RuntimeException(sprintf(
                'GitHub answered %d to the search for %s repositories with %s set.',
                $response->getStatusCode(),
                $organization,
                $property,
            ));
        }

        $answer = Json\typed(
            json: (string) $response->getBody(),
            type: Type\shape(
                elements          : [
                    'items' => Type\vec(Type\shape(
                        elements          : [
                            'name'      => Type\string(),
                            'clone_url' => Type\string(),
                            'archived'  => Type\bool(),
                        ],
                        allowUnknownFields: true,
                    )),
                ],
                allowUnknownFields: true,
            ),
        );

        return $answer['items'];
    }
}
