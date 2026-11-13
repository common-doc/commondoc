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

use Dflydev\DotAccessData\Exception\InvalidPathException;
use League\CommonMark\Exception\InvalidArgumentException;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Stringable;

use function sprintf;

/**
 * Renders the document node as a view instead of as finished HTML.
 *
 * CommonMark asks for the body through the injected child renderer, which is the
 * only correct source of node output, and the body is handed to the view
 * renderer once. The view renderer owns the layout wrap, the additional view
 * helpers and the helper state reset, so nothing here reimplements any of it.
 *
 * The page travels on the document's own data, written by `PageContextListener`,
 * which is what keeps a document rendered outside a build working with no page.
 *
 * @internal
 */
final readonly class DocumentViewRenderer implements NodeRendererInterface
{
    public function __construct(
        private TemplateRendererInterface $renderer,
    ) {}

    /**
     * @throws InvalidArgumentException when the node is not a document
     * @throws InvalidPathException from the page read, which the default value
     *         prevents in practice
     */
    #[Override]
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable|string|null
    {
        if (! $node instanceof Document) {
            throw new InvalidArgumentException(sprintf(
                'The document view renderer renders only a %s node, %s given.',
                Document::class,
                $node::class,
            ));
        }

        return $this->renderer->render('commondoc::document', [
            'documentBody' => $childRenderer->renderNodes($node->children()),
            'page'         => $this->pageFor($node),
        ]);
    }

    /**
     * The page this document belongs to, or null when the render is not part of
     * a build.
     *
     * The read carries a null default and the result is narrowed, so the null
     * case is a declared outcome rather than an assumption: a preview render and
     * a build render travel the same path.
     *
     * @throws InvalidPathException from the data read, which the default value
     *         prevents in practice
     */
    private function pageFor(Document $document): ?Page
    {
        /** @var mixed $page */
        $page = $document->data->get('commondoc.page', null);

        return $page instanceof Page ? $page : null;
    }
}
