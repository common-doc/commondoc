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

namespace CommonDocTest;

use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use League\CommonMark\Event\DocumentPreRenderEvent;
use League\CommonMark\Node\Block\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageContextListener::class)]
#[CoversMethod(PageContextListener::class, '__invoke')]
final class PageContextListenerTest extends TestCase
{
    private static function page(string $label): Page
    {
        return new Page(
            label       : $label,
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'docs/page.md',
            url         : '/page',
            versionLabel: '1.0',
        );
    }

    #[Test]
    public function keepsAPageTheDocumentAlreadyCarries(): void
    {
        $page     = self::page('Reference page');
        $document = new Document();

        $document->data->set('commondoc.page', $page);

        new PageContextListener(new PageContext())(new DocumentPreRenderEvent($document, 'html'));

        static::assertSame($page, $document->data->get('commondoc.page', null));
    }

    #[Test]
    public function readsTheContextAtEventTimeNotConstructionTime(): void
    {
        $page     = self::page('Reference page');
        $context  = new PageContext();
        $listener = new PageContextListener($context);
        $document = new Document();

        $context->set($page);

        $listener(new DocumentPreRenderEvent($document, 'html'));

        static::assertSame($page, $document->data->get('commondoc.page', null));
    }

    #[Test]
    public function writesNothingWhenNoPageIsCurrent(): void
    {
        $document = new Document();

        new PageContextListener(new PageContext())(new DocumentPreRenderEvent($document, 'html'));

        static::assertFalse($document->data->has('commondoc.page'));
    }

    #[Test]
    public function writesTheCurrentPageIntoTheDocument(): void
    {
        $page     = self::page('Reference page');
        $context  = new PageContext();
        $document = new Document();

        $context->set($page);

        new PageContextListener($context)(new DocumentPreRenderEvent($document, 'html'));

        static::assertSame($page, $document->data->get('commondoc.page', null));
    }
}
