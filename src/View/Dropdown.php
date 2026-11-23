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
 * A Bootstrap dropdown, as the markup the navbar's menus share.
 *
 * Both menus are a button and a list of links, and the only difference between them is
 * where the items come from and whether the build already knows which one is current. So
 * the markup lives here once, and each menu supplies its own items.
 *
 * `name` becomes `data-dropdown`, `data-dropdown-toggle` and `data-dropdown-menu`, which is
 * how the layout's script finds a menu whose current item the build cannot know. A menu
 * whose current item is known at build time carries it as `active` and `aria-current`, and
 * needs no script.
 *
 * A menu with nothing to choose between is rendered inert rather than as an empty list: it
 * still says which version is being read, without offering a choice that does not exist.
 * That is a separate method rather than a flag, because the two are different answers and a
 * reader of either call should not have to find out which.
 */
final readonly class Dropdown
{
    public function __construct(
        private EscaperInterface $escaper,
    ) {}

    /**
     * @param string                                                 $label The text on the button
     * @param non-empty-string                                       $name  The name the script finds it by
     * @param list<array{url: string, label: string, current: bool}> $items The links, in order
     */
    public function render(string $label, string $name, array $items): string
    {
        $escapedName = $this->escaper->escapeHtmlAttr($name);

        return sprintf(
            '<div class="dropdown me-1" data-dropdown="%s">%s'
                . '<ul class="dropdown-menu" data-dropdown-menu="%s">%s</ul>'
                . '</div>',
            $escapedName,
            $this->button($label, $name),
            $escapedName,
            implode('', array_map($this->item(...), $items)),
        );
    }

    /**
     * The button alone, naming what is being read and offering nothing.
     *
     * @param string           $label The text on the button
     * @param non-empty-string $name  The name the script finds it by
     */
    public function renderInert(string $label, string $name): string
    {
        return sprintf(
            '<div class="dropdown me-1" data-dropdown="%s">'
                . '<button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-dropdown-toggle="%s" disabled>%s</button>'
                . '</div>',
            $this->escaper->escapeHtmlAttr($name),
            $this->escaper->escapeHtmlAttr($name),
            $this->escaper->escapeHtml($label),
        );
    }

    private function button(string $label, string $name): string
    {
        return sprintf(
            '<button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" '
                . 'data-bs-toggle="dropdown" aria-expanded="false" data-dropdown-toggle="%s">%s</button>',
            $this->escaper->escapeHtmlAttr($name),
            $this->escaper->escapeHtml($label),
        );
    }

    /**
     * @param array{url: string, label: string, current: bool} $item
     */
    private function item(array $item): string
    {
        return sprintf(
            '<li><a class="dropdown-item%s" href="%s"%s>%s</a></li>',
            $item['current'] ? ' active' : '',
            $this->escaper->escapeHtmlAttr($item['url']),
            $item['current'] ? ' aria-current="page"' : '',
            $this->escaper->escapeHtml($item['label']),
        );
    }
}
