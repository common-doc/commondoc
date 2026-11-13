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

use ArrayIterator;
use Laminas\View\Model\ModelInterface;
use LogicException;
use Override;
use Traversable;

use function count;
use function sprintf;

/**
 * The identity of one converted document, as a readonly view model.
 *
 * This implements the view model contract for its state, never for its
 * placement. `LaminasViewRenderer::render()` writes to the model it is handed
 * and walks every attached child through `beforeRender()`, so a readonly page
 * cannot occupy a position in that tree. It reaches templates as the "page"
 * value on the render root instead, and templates read it through this
 * interface.
 *
 * The mutators exist to satisfy the contract and every one of them throws.
 */
// @mago-expect lint:too-many-methods - ModelInterface plus its Countable and IteratorAggregate parents require all fourteen methods.
final readonly class Page implements ModelInterface
{
    /**
     * Required fields come first, then the optional ones, alphabetical within
     * each group.
     *
     * @param list<Page> $children
     * @param non-empty-string $captureTo
     */
    // @mago-expect lint:excessive-parameter-list - a page descriptor needs all eleven inputs.
    public function __construct(
        public string $label,
        public string $projectName,
        public string $projectSlug,
        public string $sourcePath,
        public string $url,
        public string $versionLabel,
        public bool $append = false,
        public string $captureTo = 'page',
        public array $children = [],
        public string $template = 'commondoc::page',
        public bool $terminal = false,
    ) {}

    /**
     * @throws LogicException always, because a page is immutable
     */
    #[Override]
    public function addChild(
        ModelInterface $child,
        ?string $captureTo = null,
        ?bool $append = null,
    ): static {
        throw $this->immutable();
    }

    #[Override]
    public function captureTo(): string
    {
        return $this->captureTo;
    }

    #[Override]
    public function count(): int
    {
        return count($this->children);
    }

    /**
     * @return list<Page>
     */
    #[Override]
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @return Traversable<int, Page>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->children);
    }

    #[Override]
    public function getTemplate(): string
    {
        return $this->template;
    }

    #[Override]
    public function getVariable(string $name, mixed $default = null): mixed
    {
        return $this->getVariables()[$name] ?? $default;
    }

    /**
     * The typed projection of the page fields. No key is ever named `content`,
     * because Mezzio reserves that name for the layout.
     *
     * @return array<non-empty-string, mixed>
     */
    #[Override]
    public function getVariables(): array
    {
        return [
            'label'        => $this->label,
            'url'          => $this->url,
            'projectSlug'  => $this->projectSlug,
            'projectName'  => $this->projectName,
            'versionLabel' => $this->versionLabel,
            'sourcePath'   => $this->sourcePath,
            'captureTo'    => $this->captureTo,
            'append'       => $this->append,
            'terminal'     => $this->terminal,
            'children'     => $this->children,
        ];
    }

    #[Override]
    public function hasChildren(): bool
    {
        return [] !== $this->children;
    }

    #[Override]
    public function isAppend(): bool
    {
        return $this->append;
    }

    /**
     * @throws LogicException always, because a page is immutable
     */
    #[Override]
    public function setAppend(bool $append): static
    {
        throw $this->immutable();
    }

    /**
     * @throws LogicException always, because a page is immutable
     */
    #[Override]
    public function setCaptureTo(string $capture): static
    {
        throw $this->immutable();
    }

    /**
     * @throws LogicException always, because a page is immutable
     */
    #[Override]
    public function setTemplate(string $template): static
    {
        throw $this->immutable();
    }

    /**
     * @throws LogicException always, because a page is immutable
     */
    #[Override]
    public function setTerminal(bool $terminate): static
    {
        throw $this->immutable();
    }

    /**
     * @throws LogicException always, because a page is immutable
     */
    #[Override]
    public function setVariable(string $name, mixed $value): static
    {
        throw $this->immutable();
    }

    /**
     * @throws LogicException always, because a page is immutable
     */
    #[Override]
    public function setVariables(iterable $variables, bool $overwrite = false): static
    {
        throw $this->immutable();
    }

    #[Override]
    public function terminate(): bool
    {
        return $this->terminal;
    }

    private function immutable(): LogicException
    {
        return new LogicException(sprintf(
            'Cannot modify page "%s": a Page is immutable.',
            $this->label,
        ));
    }
}
