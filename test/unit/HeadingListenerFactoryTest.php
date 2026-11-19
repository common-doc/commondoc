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

use CommonDoc\Container\HeadingListenerFactory;
use CommonDoc\View\HeadingListener;
use CommonDoc\View\PageContext;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Inline\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(HeadingListenerFactory::class)]
#[CoversMethod(HeadingListenerFactory::class, '__invoke')]
final class HeadingListenerFactoryTest extends TestCase
{
    #[Test]
    public function buildsAListenerBoundToTheResolvedContext(): void
    {
        $context   = new PageContext();
        $container = $this->createStub(ContainerInterface::class);

        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => PageContext::class === $id ? $context : null,
            );

        /** @var HeadingListener $listener */
        $listener = new HeadingListenerFactory()($container, HeadingListener::class);

        $heading = new Heading(2);
        $heading->data->set(
            key  : 'attributes/id',
            value: 'install-it',
        );
        $heading->appendChild(new Text('Install it'));

        $document = new Document();
        $document->appendChild($heading);

        $listener(new DocumentParsedEvent($document));

        $sections = $context->sections();

        static::assertCount(1, $sections);
        static::assertSame('install-it', $sections[0]->fragment);
    }
}
