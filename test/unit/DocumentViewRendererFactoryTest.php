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

use CommonDoc\Container\DocumentViewRendererFactory;
use CommonDoc\View\DocumentViewRenderer;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(DocumentViewRendererFactory::class)]
#[CoversMethod(DocumentViewRendererFactory::class, '__invoke')]
final class DocumentViewRendererFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheRendererFromTheTemplateRenderer(): void
    {
        $template = $this->createStub(TemplateRendererInterface::class);
        $template->method('render')->willReturn('<html>rendered document</html>');

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => TemplateRendererInterface::class === $id ? $template : null,
            );

        $renderer = new DocumentViewRendererFactory()($container, DocumentViewRenderer::class);

        static::assertInstanceOf(DocumentViewRenderer::class, $renderer);

        $childRenderer = $this->createStub(ChildNodeRendererInterface::class);
        $childRenderer->method('renderNodes')->willReturn('<p>body</p>');

        static::assertSame(
            '<html>rendered document</html>',
            $renderer->render(new Document(), $childRenderer),
        );
    }
}
