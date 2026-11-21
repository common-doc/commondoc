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

namespace CommonDocTest\Output;

use CommonDoc\Input\Component;
use CommonDoc\Input\Release;
use CommonDoc\Input\Root;
use CommonDoc\Input\Version;
use CommonDoc\Output\ComponentIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComponentIndex::class)]
#[CoversMethod(ComponentIndex::class, '__construct')]
#[CoversMethod(ComponentIndex::class, 'entries')]
final class ComponentIndexTest extends TestCase
{
    #[Test]
    public function leavesOutTheRootThatIsTheSiteItself(): void
    {
        static::assertSame([], new ComponentIndex([new Root(path: 'docs')])->entries());
    }

    #[Test]
    public function listsAnUnreleasedRootNamedFromItsMount(): void
    {
        $entries = new ComponentIndex([new Root(
            path : 'docs',
            mount: '/email-sender/',
        )])->entries();

        static::assertSame(
            [
                [
                    'title'   => 'Email Sender',
                    'index'   => 'email-sender/index.md',
                    'target'  => 'email-sender/index.md',
                    'latest'  => '',
                    'segment' => null,
                ],
            ],
            $entries,
        );
    }

    #[Test]
    public function listsAReleasedComponentOnceHoweverManyVersionsItHas(): void
    {
        $entries = new ComponentIndex([$this->release(1), $this->release(2)])->entries();

        static::assertSame(
            [
                [
                    'title'   => 'Comp',
                    'index'   => 'comp/index.md',
                    'target'  => 'comp/v2/index.md',
                    'latest'  => 'v2.0.0',
                    'segment' => 'comp',
                ],
            ],
            $entries,
        );
    }

    private function release(int $major): Root
    {
        return new Root(
            path   : "/comp/{$major}",
            mount  : "/comp/v{$major}/",
            release: new Release(
                component: new Component('comp'),
                version  : Version::fromTag("v{$major}.0.0") ?? self::fail('The tag is not a version.'),
            ),
        );
    }
}
