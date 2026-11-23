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

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Exception\RuntimeException;
use CommonDoc\Http\CurlHttpTransport;
use InvalidArgumentException as NativeInvalidArgumentException;
use Override;
use Psl\Filesystem;
use Psl\Json;
use Psl\Regex;
use Psl\Type;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;

use function array_push;
use function getenv;
use function in_array;
use function sys_get_temp_dir;

/**
 * Reads one configured entry as every repository of an organization that opts in.
 *
 * ```php
 * ['organization' => 'webinertia', 'property' => 'doc-target', 'exclude' => ['docs.web-ware.net']]
 * ```
 *
 * The organization's custom property is the opt-in, so a repository joins the site by being
 * marked and needs no edit here. Each match is cloned with its tags and then read exactly as
 * a `repository` entry with `'versions' => true` is, so the `target` is the same one-or-a-list
 * and every consequence of a versioned entry follows. `exclude` names repositories that carry
 * the property but are not components, such as the site's own.
 *
 * The property defaults to `doc-target`. The GitHub token is read from `GITHUB_TOKEN` when one
 * is set, and is only used to search.
 *
 * @internal
 */
final readonly class OrganizationEntryReader implements EntryReaderInterface
{
    public function __construct(
        private ClientInterface $client = new CurlHttpTransport(),
        private Checkout $checkout = new Checkout(),
        private VersionEntryReader $versions = new VersionEntryReader(),
        private string $workspace = '',
    ) {}

    /**
     * @param mixed        $entry     Deliberately untyped: configuration is
     * @param list<Target> $fallbacks The site's targets, used by an entry that states none
     *
     * @return ?list<Root> Null when the entry names no organization
     *
     * @throws ClientExceptionInterface When the search cannot be sent
     * @throws NativeInvalidArgumentException When a search request cannot be composed
     * @throws RuntimeException When GitHub refuses the search
     * @throws InvalidArgumentException When a mount cannot prefix a path
     * @throws FileWriterException When a version's files cannot be written
     * @throws Filesystem\Exception\ExceptionInterface When a checkout or extraction cannot be cleared
     * @throws Json\Exception\DecodeException When GitHub's answer is not JSON
     * @throws Regex\Exception\ExceptionInterface When a tag cannot be matched, which the fixed
     *         pattern makes unreachable
     * @throws Type\Exception\ExceptionInterface When an answer is not the shape expected
     */
    #[Override]
    public function read(mixed $entry, array $fallbacks): ?array
    {
        $organization = OrganizationEntry::from($entry);

        if (null === $organization) {
            return null;
        }

        $search = new OrganizationSearch(
            client: $this->client,
            token : (string) getenv('GITHUB_TOKEN'),
        );

        $workspace = '' === $this->workspace ? sys_get_temp_dir() . '/commondoc-checkouts' : $this->workspace;
        $roots     = [];

        foreach ($search->repositories($organization->organization, $organization->property) as $repository) {
            $directory = in_array($repository->name, $organization->exclude, strict: true)
                ? null
                : $this->checkout->of($repository, $workspace);

            if (null === $directory) {
                continue;
            }

            $versioned = $this->versions->read(
                entry    : ['repository' => $directory, 'versions' => true, 'target' => $organization->target],
                fallbacks: $fallbacks,
            );

            array_push($roots, ...$versioned ?? []);
        }

        return $roots;
    }
}
