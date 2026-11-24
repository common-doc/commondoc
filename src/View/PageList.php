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

namespace CommonDoc\View;

use Laminas\Escaper\EscaperInterface;

use function array_map;
use function implode;
use function sprintf;

/**
 * The pages of one menu, as a Bootstrap list group.
 *
 * A sidebar holds one component's pages, so the list is the sidebar: the heading and the
 * rows are rendered together, and a scope with no pages renders nothing at all rather than
 * a heading over an empty box. That is what lets a site mount the sidebar beside a root
 * that publishes no pages without leaving a stray label behind.
 *
 * A row is an anchor rather than a list item wrapping one, which is the shape Bootstrap's
 * list group takes for navigation. It carries no `active`, because a built page has no
 * request; the layout's script marks the page being read by adding `active` and
 * `aria-current` to the row whose address is the one being served.
 */
final readonly class PageList
{
    public function __construct(
        private EscaperInterface $escaper,
    ) {}

    /**
     * @param string                                  $label The heading above the rows
     * @param list<array{url: string, label: string}> $items The pages, in order
     */
    public function render(string $label, array $items): string
    {
        if ([] === $items) {
            return '';
        }

        return sprintf(
            '<h2 class="h6 text-uppercase text-body-secondary px-2">%s</h2>'
                . '<div class="list-group list-group-flush">%s</div>',
            $this->escaper->escapeHtml($label),
            implode('', array_map($this->item(...), $items)),
        );
    }

    /**
     * One row.
     *
     * @param array{url: string, label: string} $item
     */
    private function item(array $item): string
    {
        return sprintf(
            '<a class="list-group-item list-group-item-action" href="%s">%s</a>',
            $this->escaper->escapeHtmlAttr($item['url']),
            $this->escaper->escapeHtml($item['label']),
        );
    }
}
