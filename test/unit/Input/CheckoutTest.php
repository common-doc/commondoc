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

use CommonDoc\Input\Candidate;
use CommonDoc\Input\Checkout;
use CommonDocTest\Support\GitCheckout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function is_file;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/**
 * Cloned from a real repository on disk, because a stubbed git would test the stub.
 */
#[CoversClass(Checkout::class)]
#[CoversMethod(Checkout::class, '__construct')]
#[CoversMethod(Checkout::class, 'of')]
final class CheckoutTest extends TestCase
{
    private string $base;

    private string $origin;

    private string $workspace;

    #[Test]
    public function answersNullWhenGitCannotProvideTheRepository(): void
    {
        static::assertNull(new Checkout()->of(new Candidate('gone', "{$this->base}/missing"), $this->workspace));
    }

    #[Test]
    public function bringsAnExistingCheckoutUpToDateInsteadOfCloningAgain(): void
    {
        $checkout   = new Checkout();
        $repository = new Candidate('fixture', $this->origin);
        $checkout->of($repository, $this->workspace);

        file_put_contents("{$this->origin}/new.md", data: "# New\n");
        GitCheckout::run($this->origin, arguments: 'add new.md');
        GitCheckout::run($this->origin, arguments: 'commit --quiet -m new');
        GitCheckout::run($this->origin, arguments: 'tag v2.0.0');

        static::assertSame("{$this->workspace}/fixture", $checkout->of($repository, $this->workspace));
        GitCheckout::run("{$this->workspace}/fixture", arguments: 'rev-parse --verify v2.0.0');
    }

    #[Test]
    public function clonesARepositoryWithItsTagsIntoTheWorkspace(): void
    {
        $directory = new Checkout()->of(new Candidate('fixture', $this->origin), $this->workspace);

        static::assertSame("{$this->workspace}/fixture", $directory);
        static::assertTrue(is_file("{$this->workspace}/fixture/README.md"));
        GitCheckout::run("{$this->workspace}/fixture", arguments: 'rev-parse --verify v1.0.0');
    }

    protected function setUp(): void
    {
        $this->base      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('commondoc-checkout-', more_entropy: true);
        $this->origin    = "{$this->base}/origin";
        $this->workspace = "{$this->base}/work";

        mkdir(
            directory: $this->origin,
            recursive: true,
        );
        GitCheckout::create($this->origin);
        GitCheckout::run($this->origin, arguments: 'tag v1.0.0');
    }

    protected function tearDown(): void
    {
        GitCheckout::remove(dirname($this->origin));
    }
}
