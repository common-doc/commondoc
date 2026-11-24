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

use CommonDoc\Output\NavigationScope;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageList;
use Laminas\View\Helper\HelperInterface;
use Mezzio\Router\Exception\RuntimeException;
use Webware\Navigation\NavigationItem;
use Webware\Navigation\View\Helper\Navigation;

use function array_map;

/**
 * The pages of the component and version being read, as the sidebar's list.
 *
 * It exists so the sidebar is a named seam rather than a loop in the layout: a theme
 * supplies its own helper of the same name, or a site replaces the markup here, without
 * editing a template. The links themselves are not decided here either. They come from the
 * same navigation container every menu comes from, built from the routing table the build
 * registered, so nothing repeats an address or a label the build already wrote.
 *
 * The menu is the one the page names, read from the build's page context: a page knows the
 * component and version it belongs to, and a layout has no other way to ask which menu it
 * is rendering inside. A document no root claims falls back to the base identifier, which
 * is the menu the site's own pages are registered under.
 *
 * No row is current in the markup, because a built page carries no request and the build
 * cannot know which page a reader is on. The layout's script marks that row.
 *
 * The label is an argument so a site can say something else without replacing the helper.
 */
final readonly class PageMenu implements HelperInterface
{
    public function __construct(
        private Navigation $navigation,
        private PageContext $context,
        private PageList $list,
    ) {}

    /**
     * One page, in the shape the list renders.
     *
     * @return array{url: string, label: string}
     *
     * @throws RuntimeException When the item's address cannot be generated
     */
    private function item(NavigationItem $item): array
    {
        return [
            'url'   => $item->getHref(),
            'label' => $item->label,
        ];
    }

    /**
     * @throws RuntimeException When an item's address cannot be generated
     */
    public function __invoke(string $label = 'Pages'): string
    {
        $scope = $this->context->current()->navigation ?? NavigationScope::BASE;

        return $this->list->render(
            label: $label,
            items: array_map($this->item(...), $this->navigation->__invoke($scope)->getItems()),
        );
    }
}
