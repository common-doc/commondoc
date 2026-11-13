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

namespace CommonDocTest;

use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageContext::class)]
#[CoversMethod(PageContext::class, 'set')]
#[CoversMethod(PageContext::class, 'clear')]
#[CoversMethod(PageContext::class, 'current')]
final class PageContextTest extends TestCase
{
    private static function page(string $label): Page
    {
        return new Page(
            label       : $label,
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'docs/page.md',
            url         : '/page',
            versionLabel: '1.0',
        );
    }

    #[Test]
    public function emptiesTheSlotOnClear(): void
    {
        $context = new PageContext();
        $context->set(self::page('Reference page'));

        $context->clear();

        static::assertNull($context->current());
    }

    #[Test]
    public function holdsThePageItWasGiven(): void
    {
        $page    = self::page('Reference page');
        $context = new PageContext();

        $context->set($page);

        static::assertSame($page, $context->current());
    }

    #[Test]
    public function replacesTheHeldPageInsteadOfAccumulating(): void
    {
        $first   = self::page('First page');
        $second  = self::page('Second page');
        $context = new PageContext();

        $context->set($first);
        $context->set($second);

        static::assertSame($second, $context->current());
    }

    #[Test]
    public function reportsNoPageWhenNothingIsSet(): void
    {
        static::assertNull(new PageContext()->current());
    }

    #[Test]
    public function toleratesAClearOnAnEmptyContext(): void
    {
        $context = new PageContext();

        $context->clear();

        static::assertNull($context->current());
    }
}
