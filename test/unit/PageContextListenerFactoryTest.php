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

use CommonDoc\Container\PageContextListenerFactory;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use League\CommonMark\Event\DocumentPreRenderEvent;
use League\CommonMark\Node\Block\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(PageContextListenerFactory::class)]
#[CoversMethod(PageContextListenerFactory::class, '__invoke')]
final class PageContextListenerFactoryTest extends TestCase
{
    #[Test]
    public function buildsAListenerBoundToTheResolvedContext(): void
    {
        $page = new Page(
            label       : 'Reference page',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'docs/page.md',
            url         : '/page',
            versionLabel: '1.0',
        );
        $context   = new PageContext();
        $container = $this->createStub(ContainerInterface::class);

        $context->set($page);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => PageContext::class === $id ? $context : null,
            );

        /** @var PageContextListener $listener */
        $listener = new PageContextListenerFactory()($container, PageContextListener::class);

        $document = new Document();

        $listener(new DocumentPreRenderEvent($document, 'html'));

        static::assertSame($page, $document->data->get('commondoc.page', null));
    }
}
