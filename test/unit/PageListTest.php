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

use CommonDoc\View\PageList;
use Laminas\Escaper\Escaper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The sidebar's markup, which is a heading and a list group or nothing at all.
 */
#[CoversClass(PageList::class)]
#[CoversMethod(PageList::class, '__construct')]
#[CoversMethod(PageList::class, 'render')]
final class PageListTest extends TestCase
{
    #[Test]
    public function escapesTheLabelAndTheAddress(): void
    {
        $html = $this->list([['url' => '/a"b/', 'label' => 'A < B']]);

        static::assertStringContainsString('href="&#x2F;a&quot;b&#x2F;"', $html);
        static::assertStringContainsString('>A &lt; B</a>', $html);
        static::assertStringNotContainsString('href="/a"b/"', $html);
    }

    #[Test]
    public function rendersEveryPageAsAListGroupRowInOrder(): void
    {
        $html = $this->list([
            ['url' => '/one/', 'label' => 'One'],
            ['url' => '/two/', 'label' => 'Two'],
        ]);

        static::assertStringContainsString('<div class="list-group list-group-flush">', $html);
        static::assertStringContainsString(
            '<a class="list-group-item list-group-item-action" href="&#x2F;one&#x2F;">One</a>'
                . '<a class="list-group-item list-group-item-action" href="&#x2F;two&#x2F;">Two</a>',
            $html,
        );
    }

    #[Test]
    public function rendersNoRowAsCurrentBecauseTheBuildHasNoRequest(): void
    {
        static::assertStringNotContainsString('active', $this->list([['url' => '/one/', 'label' => 'One']]));
    }

    #[Test]
    public function rendersNothingWithoutPages(): void
    {
        static::assertSame('', $this->list());
    }

    #[Test]
    public function takesTheHeadingItIsGiven(): void
    {
        static::assertStringContainsString(
            '<h2 class="h6 text-uppercase text-body-secondary px-2">Contents</h2>',
            $this->list([['url' => '/one/', 'label' => 'One']], label: 'Contents'),
        );
    }

    /**
     * @param list<array{url: string, label: string}> $items
     */
    private function list(array $items = [], string $label = 'Pages'): string
    {
        return new PageList(escaper: new Escaper())->render(
            label: $label,
            items: $items,
        );
    }
}
