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

use CommonDoc\Input\OrganizationEntryReader;
use CommonDoc\Input\Target;
use CommonDocTest\Support\GitCheckout;
use Laminas\Diactoros\Response\JsonResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function file_put_contents;
use function mkdir;
use function putenv;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/**
 * An organization is read end to end against a real repository: a stub answers the search, and
 * git is told by its own configuration to fetch GitHub's URL from a directory on disk, so the
 * clone, the tags and the version roots are the real ones and only the network is replaced.
 */
#[CoversClass(OrganizationEntryReader::class)]
#[CoversMethod(OrganizationEntryReader::class, '__construct')]
#[CoversMethod(OrganizationEntryReader::class, 'read')]
final class OrganizationEntryReaderTest extends TestCase
{
    public const string URL = 'https://github.com/example/fixture.git';

    private string $base;

    #[Test]
    public function clonesEveryRepositoryTheSearchFindsAndHandsItToTheVersionReader(): void
    {
        $roots = $this->reader()->read(
            ['organization' => 'example', 'target' => ['docs/v{major}']],
            [new Target()],
        );

        // The checkout is the real one, with its tags.
        GitCheckout::run("{$this->base}/work/fixture", arguments: 'rev-parse --verify v1.0.0');

        // The roots themselves are `VersionRootTest`'s to prove. Here git is told to read
        // GitHub's URL from disk, and `remote get-url` expands that rewrite too, so the clone's
        // origin no longer names GitHub and the version reader rightly finds no component in it.
        static::assertSame([], $roots);
    }

    #[Test]
    public function isNotThisReadersEntryWithoutAnOrganization(): void
    {
        static::assertNull($this->reader()->read(['repository' => 'x'], [new Target()]));
    }

    #[Test]
    public function leavesOutAnExcludedRepositoryWithoutCloningIt(): void
    {
        $roots = $this->reader()->read(
            ['organization' => 'example', 'exclude' => ['fixture'], 'target' => 'docs/v{major}'],
            [new Target()],
        );

        static::assertSame([], $roots);
        static::assertDirectoryDoesNotExist("{$this->base}/work/fixture");
    }

    #[Test]
    public function skipsARepositoryGitCannotClone(): void
    {
        putenv('GIT_CONFIG_COUNT');

        static::assertSame([], $this->reader()->read(['organization' => 'example'], [new Target()]));
    }

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('commondoc-org-', more_entropy: true);
        $origin     = "{$this->base}/origin";

        mkdir(
            directory: "{$origin}/docs/v1",
            recursive: true,
        );
        GitCheckout::create($origin);
        file_put_contents("{$origin}/docs/v1/index.md", data: "# One\n");
        GitCheckout::run($origin, arguments: 'add docs');
        GitCheckout::run($origin, arguments: 'commit --quiet -m docs');
        GitCheckout::run($origin, arguments: 'tag v1.0.0');

        // Git's own configuration, set for this process only: GitHub's URL is read from disk.
        putenv('GIT_CONFIG_COUNT=1');
        putenv("GIT_CONFIG_KEY_0=url.{$origin}.insteadOf");
        putenv('GIT_CONFIG_VALUE_0=' . self::URL);
    }

    protected function tearDown(): void
    {
        putenv('GIT_CONFIG_COUNT');
        putenv('GIT_CONFIG_KEY_0');
        putenv('GIT_CONFIG_VALUE_0');
        GitCheckout::remove($this->base);
    }

    private function reader(): OrganizationEntryReader
    {
        return new OrganizationEntryReader(
            client   : new class implements ClientInterface {
                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    return new JsonResponse([
                        'items' => [
                            ['name' => 'fixture', 'clone_url' => OrganizationEntryReaderTest::URL, 'archived' => false],
                        ],
                    ]);
                }
            },
            workspace: "{$this->base}/work",
        );
    }
}
