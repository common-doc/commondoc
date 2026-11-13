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

use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\Page;
use League\CommonMark\Exception\InvalidArgumentException;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;

#[CoversClass(DocumentViewRenderer::class)]
#[CoversMethod(DocumentViewRenderer::class, 'render')]
final class DocumentViewRendererTest extends TestCase
{
    /**
     * @param list<Node> $children
     */
    private static function documentWith(array $children): Document
    {
        $document = new Document();

        foreach ($children as $child) {
            $document->appendChild($child);
        }

        return $document;
    }

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
    public function asksTheChildRendererOnceForTheDocumentChildren(): void
    {
        $children = [new Paragraph(), new Paragraph()];
        $document = self::documentWith($children);

        $childRenderer = $this->createMock(ChildNodeRendererInterface::class);
        $childRenderer
            ->expects($this->once())
            ->method('renderNodes')
            ->with($children)
            ->willReturn('<p>body</p>');

        new DocumentViewRenderer($this->createStub(TemplateRendererInterface::class))
            ->render($document, $childRenderer);
    }

    #[Test]
    public function handsTheChildRendererOutputToTheTemplateUnchanged(): void
    {
        $body   = "<p>first</p>\n<p>second &amp; third</p>";
        $name   = null;
        $params = null;

        new DocumentViewRenderer($this->templateCapturing($name, $params))
            ->render(self::documentWith([new Paragraph()]), $this->childRendererReturning($body));

        static::assertSame($body, $params['documentBody'] ?? null);
    }

    #[Test]
    public function passesANullPageWhenTheDocumentCarriesNone(): void
    {
        $name   = null;
        $params = null;

        new DocumentViewRenderer($this->templateCapturing($name, $params))
            ->render(new Document(), $this->childRendererReturning('<p>body</p>'));

        static::assertSame('commondoc::document', $name);
        static::assertSame(['documentBody' => '<p>body</p>', 'page' => null], $params);
    }

    #[Test]
    public function passesTheBodyAndThePageToTheBodyTemplate(): void
    {
        $page     = self::page('Reference page');
        $document = self::documentWith([new Paragraph()]);
        $document->data->set('commondoc.page', $page);

        $name   = null;
        $params = null;

        new DocumentViewRenderer($this->templateCapturing($name, $params))
            ->render($document, $this->childRendererReturning('<p>body</p>'));

        static::assertSame('commondoc::document', $name);
        static::assertSame(['documentBody' => '<p>body</p>', 'page' => $page], $params);
    }

    #[Test]
    public function rejectsANodeThatIsNotADocument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document');

        new DocumentViewRenderer($this->createStub(TemplateRendererInterface::class))
            ->render(new Paragraph(), $this->childRendererReturning('<p>body</p>'));
    }

    #[Test]
    public function rendersTheDocumentChildrenThroughTheChildRenderer(): void
    {
        $children      = [new Paragraph(), new Paragraph()];
        $document      = self::documentWith($children);
        $receivedNodes = [];

        $childRenderer = $this->createStub(ChildNodeRendererInterface::class);
        $childRenderer
            ->method('renderNodes')
            ->willReturnCallback(static function (iterable $nodes) use (&$receivedNodes): string {
                $receivedNodes = [...$nodes];

                return '<p>body</p>';
            });

        new DocumentViewRenderer($this->createStub(TemplateRendererInterface::class))
            ->render($document, $childRenderer);

        static::assertSame($children, $receivedNodes);
    }

    #[Test]
    public function rendersTheDocumentWhenTheChildRendererReturnsAnEmptyString(): void
    {
        $name   = null;
        $params = null;

        $result = new DocumentViewRenderer($this->templateCapturing($name, $params))
            ->render(self::documentWith([new Paragraph()]), $this->childRendererReturning(''));

        static::assertSame('commondoc::document', $name);
        static::assertSame('', $params['documentBody'] ?? null);
        static::assertNotSame('', $result);
    }

    #[Test]
    public function returnsTheRenderedBodyTemplateUnchanged(): void
    {
        $template = $this->createStub(TemplateRendererInterface::class);
        $template->method('render')->willReturn('<html>rendered document</html>');

        static::assertSame(
            '<html>rendered document</html>',
            new DocumentViewRenderer($template)
                ->render(new Document(), $this->childRendererReturning('<p>body</p>')),
        );
    }

    private function childRendererReturning(string $body): ChildNodeRendererInterface
    {
        $childRenderer = $this->createStub(ChildNodeRendererInterface::class);
        $childRenderer->method('renderNodes')->willReturn($body);

        return $childRenderer;
    }

    /**
     * @param array<non-empty-string, mixed>|null $receivedParams
     */
    private function templateCapturing(?string &$receivedName, ?array &$receivedParams): TemplateRendererInterface
    {
        $template = $this->createStub(TemplateRendererInterface::class);
        $template
            ->method('render')
            ->willReturnCallback(
                static function (string $name, array|object $params = []) use (
                    &$receivedName,
                    &$receivedParams,
                ): string {
                    $receivedName   = $name;
                    $receivedParams = is_array($params) ? $params : [];

                    return '<html>rendered document</html>';
                },
            );

        return $template;
    }
}
