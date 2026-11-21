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

use CommonDoc\Input\Root;
use CommonDoc\Output\NavigationScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NavigationScope::class)]
#[CoversMethod(NavigationScope::class, '__construct')]
#[CoversMethod(NavigationScope::class, 'of')]
final class NavigationScopeTest extends TestCase
{
    #[Test]
    public function fallsBackToTheBaseIdentifierWhenNoRootHoldsTheDocument(): void
    {
        static::assertSame(NavigationScope::BASE, new NavigationScope([])->of('index.md'));
        static::assertSame(
            NavigationScope::BASE,
            new NavigationScope([new Root(
                path : 'a',
                mount: '/x/',
            )])->of('y.md'),
        );
    }

    #[Test]
    public function letsTheLongestMountWinWhenOneIsInsideAnother(): void
    {
        $scope = new NavigationScope([
            new Root(
                path : 'a',
                mount: '/',
            ),
            new Root(
                path : 'b',
                mount: '/email/',
            ),
        ]);

        static::assertSame('commondoc:/email/', $scope->of('email/index.md'));
        static::assertSame('commondoc:/', $scope->of('index.md'));
    }

    #[Test]
    public function namesTheMenuAfterTheMountThatHoldsTheDocument(): void
    {
        $scope = new NavigationScope([
            new Root(
                path : 'a',
                mount: '/bus/v1/',
            ),
            new Root(
                path : 'b',
                mount: '/bus/v2/',
            ),
        ]);

        static::assertSame('commondoc:/bus/v1/', $scope->of('bus/v1/guide/setup.md'));
        static::assertSame('commondoc:/bus/v2/', $scope->of('bus/v2/index.md'));
    }
}
