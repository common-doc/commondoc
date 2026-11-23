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

use CommonDoc\View\Dropdown;
use CommonDoc\View\PageContext;
use Laminas\View\Helper\HelperInterface;

use function array_find;
use function count;

/**
 * The versions of the component being read, as a navbar dropdown.
 *
 * It reads the page from the context rather than a placeholder, because the layout renders
 * after the document template but still inside the conversion, so the page is still the one
 * the build set. That is what lets the navbar name the version being read without the
 * document template handing anything over.
 *
 * The current version is known here, unlike the current component: the page carries the
 * versions the build assembled, each saying whether it is the one being read. So the markup
 * is complete in the file and no script is involved.
 *
 * A component with one version gets a disabled button naming that version, which keeps the
 * navbar's shape steady and still answers "which version is this". A page that belongs to
 * no versioned component, and a render outside a build, get nothing at all.
 */
final readonly class VersionSwitch implements HelperInterface
{
    public function __construct(
        private PageContext $context,
        private Dropdown $dropdown,
    ) {}

    /**
     * @param array{label: string, url: string, current: bool} $version
     */
    private static function isCurrent(array $version): bool
    {
        return $version['current'];
    }

    public function __invoke(): string
    {
        $versions = $this->context->current()->versions ?? [];

        if ([] === $versions) {
            return '';
        }

        $found = array_find($versions, self::isCurrent(...));

        // A page whose versions are all historical names the first, so the button still
        // says something rather than falling back to a word the menu does not use.
        $label = $found['label'] ?? $versions[0]['label'];

        return 1 === count($versions)
            ? $this->dropdown->renderInert(
                label: $label,
                name : 'versions',
            )
            : $this->dropdown->render(
                label: $label,
                name : 'versions',
                items: $versions,
            );
    }
}
