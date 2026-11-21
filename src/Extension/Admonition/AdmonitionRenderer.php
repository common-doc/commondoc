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

namespace CommonDoc\Extension\Admonition;

use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Override;
use Stringable;

use function sprintf;
use function trim;

/**
 * Renders an admonition as the markup the default theme styles.
 *
 * ```html
 * <div class="admonition admonition-warning alert alert-warning" role="note">
 *     <p class="admonition-title"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Warning</p>
 *     ...
 * </div>
 * ```
 *
 * @api
 */
final readonly class AdmonitionRenderer implements NodeRendererInterface
{
    /**
     * @throws InvalidArgumentException When the node is not an admonition
     */
    #[Override]
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable|string
    {
        if (! $node instanceof Admonition) {
            throw new InvalidArgumentException(sprintf(
                'The admonition renderer renders only a %s node, %s given.',
                Admonition::class,
                $node::class,
            ));
        }

        $kind  = $node->kind;
        $icon  = new HtmlElement('i', ['class' => "bi {$kind->icon()}", 'aria-hidden' => 'true'], '');
        $title = new HtmlElement('p', ['class' => 'admonition-title'], (string) $icon . " {$kind->title()}");

        return new HtmlElement(
            'div',
            [
                'class' => trim(sprintf('admonition admonition-%s alert %s', $kind->value, $kind->alert())),
                'role'  => 'note',
            ],
            "\n{$title}\n" . $childRenderer->renderNodes($node->children()) . "\n",
        );
    }
}
