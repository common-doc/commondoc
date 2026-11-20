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

use CommonDoc\Input\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A source link has to be right without being checked by anything downstream: it
 * points at a repository the build does not read, so a wrong URL publishes a
 * working-looking page whose link is dead.
 *
 * Both remote spellings are covered because a checkout may have been cloned either
 * way, and the documentation being built must not depend on which.
 */
#[CoversClass(Source::class)]
#[CoversMethod(Source::class, 'at')]
#[CoversMethod(Source::class, 'fromRemote')]
final class SourceTest extends TestCase
{
    #[Test]
    public function buildsAURLForTheCommitThatWasRead(): void
    {
        $source = new Source('https://github.com/example/repo', 'abc123');

        static::assertSame(
            'https://github.com/example/repo/blob/abc123/benchmarks/run.php',
            $source->at('benchmarks/run.php'),
        );
    }

    #[Test]
    public function encodesASegmentThatWouldOtherwiseEndTheUrl(): void
    {
        // A space or a `#` in a path ends the URL or the request early, so every
        // segment is encoded rather than pasted.
        $source = new Source('https://github.com/example/repo', 'abc123');

        static::assertSame(
            'https://github.com/example/repo/blob/abc123/notes/a%20b%23c.md',
            $source->at('notes/a b#c.md'),
        );
    }

    #[Test]
    public function readsAnHttpsRemote(): void
    {
        $source = Source::fromRemote(
            remote: 'https://github.com/example/repo.git',
            ref   : 'abc123',
            prefix: 'docs/2.0',
        );

        static::assertInstanceOf(Source::class, $source);
        static::assertSame('https://github.com/example/repo', $source->url);
        static::assertSame('abc123', $source->ref);
        static::assertSame('docs/2.0', $source->prefix);
    }

    #[Test]
    public function readsAnSshRemoteAsTheSameRepository(): void
    {
        $source = Source::fromRemote(
            remote: 'git@github.com:example/repo.git',
            ref   : 'abc123',
        );

        static::assertInstanceOf(Source::class, $source);
        static::assertSame('https://github.com/example/repo', $source->url);
    }

    #[Test]
    public function readsTheUrlSpellingOfAnSshRemote(): void
    {
        // A checkout may carry either spelling of the same remote, and the published
        // link must not depend on which one the person who cloned it used.
        $source = Source::fromRemote(
            remote: 'ssh://git@github.com/example/repo.git',
            ref   : 'abc123',
        );

        static::assertInstanceOf(Source::class, $source);
        static::assertSame('https://github.com/example/repo', $source->url);
    }

    #[Test]
    public function refusesAHostItCannotLinkTo(): void
    {
        // A `/blob/<ref>/<path>` URL only exists on a host that serves one, so a
        // remote that is not GitHub answers null and the caller keeps its refusal.
        static::assertNull(Source::fromRemote(
            remote: 'https://gitlab.com/example/repo.git',
            ref   : 'abc123',
        ));
        static::assertNull(Source::fromRemote(
            remote: '/some/local/path',
            ref   : 'abc123',
        ));
    }

    #[Test]
    public function skipsAnEmptySegmentInThePath(): void
    {
        // A path with a doubled or trailing separator names the same file, and the
        // empty segment would otherwise become an empty element in the URL.
        $source = new Source('https://github.com/example/repo', 'abc123');

        static::assertSame(
            'https://github.com/example/repo/blob/abc123/benchmarks/run.php',
            $source->at('/benchmarks//run.php'),
        );
    }
}
