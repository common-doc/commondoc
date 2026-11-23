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

use CommonDoc\Output\RouteProvider;
use CommonDoc\View\Dropdown;
use Laminas\View\Helper\HelperInterface;
use Mezzio\Router\Exception\RuntimeException;
use Webware\Navigation\NavigationItem;
use Webware\Navigation\View\Helper\Navigation;

use function array_map;

/**
 * The released components as a navbar dropdown.
 *
 * It exists so the menu is a named seam rather than a loop in the layout: a theme supplies
 * its own helper of the same name, or a site replaces the markup here, without editing a
 * template. The links themselves are not decided here either. They come from the same
 * navigation container every menu comes from, built from the routing table the build
 * registered, so nothing repeats an address or a label the build already wrote.
 *
 * No item is current in the markup, because a built page carries no request and the build
 * cannot know which component a reader is in. The layout's script marks that item and names
 * the component on the button, finding this menu by the `data-dropdown` name it passes to
 * `Dropdown`.
 *
 * The label is an argument so a site can say something else without replacing the helper.
 */
final readonly class ComponentMenu implements HelperInterface
{
    public function __construct(
        private Navigation $navigation,
        private Dropdown $dropdown,
    ) {}

    /**
     * One link, in the shape the dropdown renders.
     *
     * @return array{url: string, label: string, current: bool}
     *
     * @throws RuntimeException When the item's address cannot be generated
     */
    private function item(NavigationItem $item): array
    {
        return [
            'url'     => $item->getHref(),
            'label'   => $item->label,
            'current' => false,
        ];
    }

    /**
     * @throws RuntimeException When an item's address cannot be generated
     */
    public function __invoke(string $label = 'Components'): string
    {
        $items = $this->navigation->__invoke(RouteProvider::COMPONENTS)->getItems();

        if ([] === $items) {
            return '';
        }

        return $this->dropdown->render(
            label: $label,
            name : 'components',
            items: array_map($this->item(...), $items),
        );
    }
}
