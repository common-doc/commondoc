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

namespace CommonDocTest\Input;

use CommonDoc\Exception\RuntimeException;
use CommonDoc\Input\OrganizationSearch;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Json\Exception\DecodeException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function array_shift;
use function bin2hex;
use function count;
use function random_bytes;
use function range;

/**
 * The search is asked of a stub client, because the behaviour under test is what is sent and
 * what is made of the answer, and a real request would test GitHub instead.
 */
#[CoversClass(OrganizationSearch::class)]
#[CoversMethod(OrganizationSearch::class, '__construct')]
#[CoversMethod(OrganizationSearch::class, 'repositories')]
final class OrganizationSearchTest extends TestCase
{
    #[Test]
    public function asksForTheOrganizationsRepositoriesWithThePropertyTrue(): void
    {
        $seen   = [];
        $client = $this->client(
            [$this->answer([['name' => 'a', 'clone_url' => 'https://x/a.git', 'archived' => false]])],
            $seen,
        );

        $found = new OrganizationSearch($client)->repositories(
            organization: 'webinertia',
            property    : 'doc-target',
        );

        static::assertCount(1, $found);
        static::assertSame('a', $found[0]->name);
        static::assertSame('https://x/a.git', $found[0]->url);
        static::assertStringContainsString(
            'q=org%3Awebinertia+props.doc-target%3Atrue',
            (string) $seen[0]->getUri(),
        );
        static::assertSame('application/vnd.github+json', $seen[0]->getHeaderLine('Accept'));
        static::assertFalse($seen[0]->hasHeader('Authorization'));
    }

    #[Test]
    public function leavesOutAnArchivedRepository(): void
    {
        $client = $this->client([$this->answer([
            ['name' => 'live', 'clone_url' => 'https://x/live.git', 'archived' => false],
            ['name' => 'old', 'clone_url' => 'https://x/old.git', 'archived' => true],
        ])]);

        $found = new OrganizationSearch($client)->repositories(
            organization: 'o',
            property    : 'p',
        );

        static::assertSame(['live'], [$found[0]->name]);
        static::assertCount(1, $found);
    }

    #[Test]
    public function readsEveryPageUntilOneIsShort(): void
    {
        $full = [];

        foreach (range(
            start: 1,
            end  : 100,
        ) as $index) {
            $full[] = ['name' => "r{$index}", 'clone_url' => "https://x/r{$index}.git", 'archived' => false];
        }

        $seen   = [];
        $client = $this->client(
            [
                $this->answer($full),
                $this->answer([['name' => 'last', 'clone_url' => 'https://x/l.git', 'archived' => false]]),
            ],
            $seen,
        );

        $found = new OrganizationSearch($client)->repositories(
            organization: 'o',
            property    : 'p',
        );

        static::assertCount(101, $found);
        static::assertCount(2, $seen);
        static::assertStringContainsString('page=2', (string) $seen[1]->getUri());
    }

    #[Test]
    public function refusesAnAnswerThatIsNotSuccess(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GitHub answered 403');

        new OrganizationSearch($this->client([new Response\EmptyResponse(403)]))->repositories(
            organization: 'o',
            property    : 'p',
        );
    }

    #[Test]
    public function refusesAnAnswerThatIsNotTheDocumentedShape(): void
    {
        $this->expectException(DecodeException::class);

        new OrganizationSearch($this->client([$this->json(['items' => 'nope'])]))->repositories(
            organization: 'o',
            property    : 'p',
        );
    }

    #[Test]
    public function sendsTheTokenWhenThereIsOne(): void
    {
        $seen   = [];
        $token  = bin2hex(random_bytes(8));
        $client = $this->client([$this->answer([])], $seen);

        new OrganizationSearch($client, token: $token)->repositories(
            organization: 'o',
            property    : 'p',
        );

        static::assertSame("Bearer {$token}", $seen[0]->getHeaderLine('Authorization'));
    }

    /**
     * @param list<array{name: string, clone_url: string, archived: bool}> $items
     */
    private function answer(array $items): ResponseInterface
    {
        return $this->json(['total_count' => count($items), 'items' => $items]);
    }

    /**
     * A client that answers each call with the next response and records the requests.
     *
     * @param list<ResponseInterface> $responses
     * @param list<RequestInterface>  $seen
     */
    private function client(array $responses, array &$seen = []): ClientInterface
    {
        return new class($responses, $seen) implements ClientInterface {
            /**
             * @param list<ResponseInterface> $responses
             * @param list<RequestInterface>  $seen
             */
            public function __construct(
                private array $responses,
                private array &$seen,
            ) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->seen[] = $request;

                return array_shift($this->responses) ?? new Response\EmptyResponse(500);
            }
        };
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function json(array $body): ResponseInterface
    {
        return new Response\JsonResponse($body);
    }
}
