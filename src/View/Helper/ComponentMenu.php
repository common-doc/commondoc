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

namespace CommonDoc\View\Helper;

use CommonDoc\Output\RouteRegistrar;
use Laminas\Escaper\EscaperInterface;
use Laminas\View\Helper\HelperInterface;
use Webware\Navigation\NavigationItem;
use Webware\Navigation\View\Helper\Navigation;

use function array_map;
use function implode;
use function sprintf;

/**
 * The released components as a Bootstrap dropdown, for the navbar.
 *
 * It exists so the menu is a named seam rather than a loop in the layout: a theme supplies
 * its own helper of the same name, or a site replaces the markup here, without editing a
 * template. The links themselves are not decided here either. They come from the same
 * navigation container every menu comes from, built from the routing table the build
 * registered, so nothing repeats an address or a label the build already wrote.
 *
 * The button names the component being read, and that item is marked, in the layout's own
 * script rather than here: a built page carries no request, so which component is current
 * is the browser's to answer, and the script reads the addresses this helper emits. The
 * `data-components`, `data-components-toggle` and `data-components-menu` attributes are
 * the contract with that script.
 *
 * The label is an argument so a site can say something else without replacing the helper.
 */
final readonly class ComponentMenu implements HelperInterface
{
    public function __construct(
        private Navigation $navigation,
        private EscaperInterface $escaper,
    ) {}

    public function __invoke(string $label = 'Components'): string
    {
        $items = $this->navigation->__invoke(RouteRegistrar::COMPONENTS)->getItems();

        if ([] === $items) {
            return '';
        }

        $links = array_map(
            fn(NavigationItem $item): string => sprintf(
                '<li><a class="dropdown-item" href="%s">%s</a></li>',
                $this->escaper->escapeHtmlAttr($item->getHref()),
                $this->escaper->escapeHtml($item->label),
            ),
            $items,
        );

        return sprintf(
            '<div class="dropdown me-1" data-components>'
                . '<button class="btn btn-outline-secondary btn-sm dropdown-toggle" '
                . 'type="button" data-bs-toggle="dropdown" aria-expanded="false" '
                . 'data-components-toggle>%s</button>'
                . '<ul class="dropdown-menu" data-components-menu>%s</ul>'
                . '</div>',
            $this->escaper->escapeHtml($label),
            implode('', $links),
        );
    }
}
