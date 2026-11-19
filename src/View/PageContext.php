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

/**
 * The page being converted, for the duration of one conversion.
 *
 * One slot, no stack and no static state: a caller converts one document at a
 * time and brackets the conversion with `set()` and `clear()`. The listener
 * reads this slot on `DocumentPreRenderEvent`, so nothing else in the render
 * path needs to be told about the page.
 */
final class PageContext
{
    private ?Page $page = null;

    /** @var list<Heading> */
    private array $sections = [];

    public function clear(): void
    {
        $this->page     = null;
        $this->sections = [];
    }

    public function current(): ?Page
    {
        return $this->page;
    }

    /**
     * This document's headings, as link targets.
     *
     * Cleared with the page, so one document's sections never leak into the next. The
     * build converts one document at a time through this context, which is what makes a
     * single value safe to hold.
     *
     * @return list<Heading>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    public function set(Page $page): void
    {
        $this->page = $page;
    }

    /**
     * @param list<Heading> $sections
     */
    public function setSections(array $sections): void
    {
        $this->sections = $sections;
    }
}
