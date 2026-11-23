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

use CommonDoc\View\Dropdown;
use Laminas\Escaper\Escaper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function substr_count;

/**
 * The markup the navbar's two menus share.
 */
#[CoversClass(Dropdown::class)]
#[CoversMethod(Dropdown::class, '__construct')]
#[CoversMethod(Dropdown::class, 'render')]
#[CoversMethod(Dropdown::class, 'renderInert')]
final class DropdownTest extends TestCase
{
    #[Test]
    public function escapesTheLabelAndTheAddress(): void
    {
        $html = $this->dropdown()->render(
            label: 'A < B',
            name : 'versions',
            items: [['url' => '/a"b/', 'label' => 'C < D', 'current' => false]],
        );

        static::assertStringContainsString('href="&#x2F;a&quot;b&#x2F;"', $html);
        static::assertStringContainsString('>C &lt; D</a>', $html);
        static::assertStringContainsString('>A &lt; B</button>', $html);
    }

    #[Test]
    public function keepsTheButtonAndDropsTheListWhenInert(): void
    {
        $html = $this->dropdown()->renderInert(
            label: 'v1',
            name : 'versions',
        );

        static::assertStringContainsString('disabled>v1</button>', $html);
        static::assertStringNotContainsString('dropdown-menu', $html);
    }

    #[Test]
    public function marksTheCurrentItem(): void
    {
        $html = $this->dropdown()->render(
            label: 'v1',
            name : 'versions',
            items: $this->items(),
        );

        static::assertStringContainsString(
            '<a class="dropdown-item active" href="&#x2F;comp&#x2F;v1&#x2F;" aria-current="page">v1</a>',
            $html,
        );
    }

    #[Test]
    public function namesItselfForTheScriptToFind(): void
    {
        $html = $this->dropdown()->render(
            label: 'Components',
            name : 'components',
            items: $this->items(),
        );

        static::assertStringContainsString('data-dropdown="components"', $html);
        static::assertStringContainsString('data-dropdown-toggle="components"', $html);
        static::assertStringContainsString('data-dropdown-menu="components"', $html);
    }

    #[Test]
    public function rendersOneItemPerEntry(): void
    {
        $html = $this->dropdown()->render(
            label: 'Components',
            name : 'components',
            items: $this->items(),
        );

        static::assertSame(2, substr_count(
            haystack: $html,
            needle  : 'class="dropdown-item',
        ));
    }

    private function dropdown(): Dropdown
    {
        return new Dropdown(new Escaper());
    }

    /**
     * @return list<array{url: string, label: string, current: bool}>
     */
    private function items(): array
    {
        return [
            ['url' => '/comp/v2/', 'label' => 'v2', 'current' => false],
            ['url' => '/comp/v1/', 'label' => 'v1', 'current' => true],
        ];
    }
}
