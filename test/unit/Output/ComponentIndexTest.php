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
use CommonDocTest\Support\AbstractTemporaryTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;

use function file_put_contents;
use function mkdir;

/**
 * Which roots a site offers as components.
 *
 * A released root answers from its tags; any other answers from what is on disk, so the
 * fixtures that exercise one write a directory rather than naming a path that does not exist.
 */
#[CoversClass(ComponentIndex::class)]
#[CoversMethod(ComponentIndex::class, '__construct')]
#[CoversMethod(ComponentIndex::class, 'entries')]
final class ComponentIndexTest extends AbstractTemporaryTree
{
    #[Test]
    public function leavesOutARootThatHoldsNoDocument(): void
    {
        // A theme directory is mounted to publish files, not to be read: nothing in it is a
        // page, so a card for it would link to a page that does not exist.
        $root  = $this->makeTree();
        $theme = "{$root}/theme";
        mkdir(directory: $theme);
        file_put_contents(
            filename: "{$theme}/site.css",
            data    : 'body {}',
        );

        static::assertSame(
            [],
            new ComponentIndex([new Root(
                path : $theme,
                mount: '/theme/',
            )])->entries(),
        );
    }

    #[Test]
    public function leavesOutTheRootThatIsTheSiteItself(): void
    {
        static::assertSame([], new ComponentIndex([new Root(path: $this->makeTree())])->entries());
    }

    #[Test]
    public function listsAnUnreleasedRootNamedFromItsMount(): void
    {
        $this->makeTree();
        file_put_contents(
            filename: "{$this->tree}/readme.md",
            data    : '# Email sender',
        );

        $entries = new ComponentIndex([new Root(
            path : $this->tree,
            mount: '/email-sender/',
        )])->entries();

        static::assertSame(
            [
                [
                    'title'   => 'Email Sender',
                    'index'   => 'email-sender/readme.md',
                    'target'  => 'email-sender/readme.md',
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
                    'target'  => 'comp/v2/readme.md',
                    'latest'  => 'v2.0.0',
                    'segment' => 'comp',
                ],
            ],
            $entries,
        );
    }

    protected function tearDown(): void
    {
        $this->removeTree();
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
