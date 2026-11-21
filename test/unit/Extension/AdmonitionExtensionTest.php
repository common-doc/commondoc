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

namespace CommonDocTest\Extension;

use CommonDoc\Extension\Admonition\Admonition;
use CommonDoc\Extension\Admonition\AdmonitionExtension;
use CommonDoc\Extension\Admonition\AdmonitionKind;
use CommonDoc\Extension\Admonition\AdmonitionListener;
use CommonDoc\Extension\Admonition\AdmonitionMarker;
use CommonDoc\Extension\Admonition\AdmonitionRenderer;
use InvalidArgumentException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The extension is exercised through a real CommonMark environment, because what matters is
 * the HTML a document ends up with and a stubbed tree would test the stub.
 */
#[CoversClass(AdmonitionExtension::class)]
#[CoversClass(AdmonitionListener::class)]
#[CoversClass(AdmonitionMarker::class)]
#[CoversClass(AdmonitionRenderer::class)]
#[CoversClass(Admonition::class)]
#[CoversClass(AdmonitionKind::class)]
#[CoversMethod(AdmonitionExtension::class, 'register')]
#[CoversMethod(AdmonitionListener::class, '__invoke')]
#[CoversMethod(AdmonitionMarker::class, 'in')]
#[CoversMethod(AdmonitionMarker::class, 'remove')]
#[CoversMethod(AdmonitionRenderer::class, 'render')]
#[CoversMethod(AdmonitionKind::class, 'title')]
#[CoversMethod(AdmonitionKind::class, 'alert')]
#[CoversMethod(AdmonitionKind::class, 'icon')]
final class AdmonitionExtensionTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function kinds(): array
    {
        return [
            'note'      => ['NOTE', 'admonition admonition-note alert alert-info', 'bi-info-circle-fill', 'Note'],
            'tip'       => ['TIP', 'admonition admonition-tip alert alert-success', 'bi-lightbulb-fill', 'Tip'],
            'important' => [
                'IMPORTANT',
                'admonition admonition-important alert"',
                'bi-exclamation-diamond-fill',
                'Important',
            ],
            'warning'   => [
                'WARNING',
                'admonition admonition-warning alert alert-warning',
                'bi-exclamation-triangle-fill',
                'Warning',
            ],
            'caution'   => [
                'CAUTION',
                'admonition admonition-caution alert alert-danger',
                'bi-x-octagon-fill',
                'Caution',
            ],
        ];
    }

    #[Test]
    public function convertsAnAdmonitionNestedInsideAnotherBlockquote(): void
    {
        $html = $this->convert("> quoted\n>\n> > [!WARNING]\n> > inner\n");

        static::assertStringContainsString('<blockquote>', $html);
        static::assertStringContainsString('admonition-warning', $html);
    }

    #[Test]
    public function keepsEveryBlockInsideTheAdmonition(): void
    {
        $html = $this->convert("> [!TIP]\n> First.\n>\n> - one\n> - two\n>\n> ```php\n> echo 1;\n> ```\n");

        static::assertStringContainsString('<p>First.</p>', $html);
        static::assertStringContainsString('<li>one</li>', $html);
        static::assertStringContainsString('<pre><code class="language-php">echo 1;', $html);
    }

    #[Test]
    public function leavesABlockquoteThatDoesNotOpenWithAParagraphAlone(): void
    {
        static::assertStringNotContainsString('admonition', $this->convert("> - [!NOTE]\n"));
    }

    #[Test]
    public function leavesAMarkerFollowedByEmphasisOnItsLineAlone(): void
    {
        static::assertStringNotContainsString('admonition', $this->convert("> [!NOTE]*x*\n"));
    }

    #[Test]
    public function leavesAMarkerFollowedByTextOnItsLineAlone(): void
    {
        $html = $this->convert("> [!NOTE] some text\n");

        static::assertStringContainsString('<blockquote>', $html);
        static::assertStringNotContainsString('admonition', $html);
    }

    #[Test]
    public function leavesAnOrdinaryBlockquoteAlone(): void
    {
        static::assertSame("<blockquote>\n<p>Just a quote.</p>\n</blockquote>\n", $this->convert("> Just a quote.\n"));
    }

    #[Test]
    public function leavesAnUnknownMarkerAlone(): void
    {
        static::assertStringNotContainsString('admonition', $this->convert("> [!DANGER]\n> x\n"));
    }

    #[Test]
    public function readsNoMarkerFromAParagraphThatHasNone(): void
    {
        static::assertNull(AdmonitionMarker::in(new Paragraph()));
    }

    #[Test]
    public function readsTheMarkerWithoutRegardToCase(): void
    {
        static::assertStringContainsString('admonition-warning', $this->convert("> [!warning]\n> x\n"));
    }

    #[Test]
    public function refusesToRenderANodeThatIsNotAnAdmonition(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdmonitionRenderer()->render(new Document(), $this->createStub(ChildNodeRendererInterface::class));
    }

    #[Test]
    public function rendersAnAdmonitionThatIsOnlyAMarker(): void
    {
        $html = $this->convert("> [!NOTE]\n");

        static::assertStringContainsString('admonition-note', $html);
        static::assertStringNotContainsString('<p>[!NOTE]', $html);
    }

    #[Test]
    #[DataProvider('kinds')]
    public function rendersEachKindAsTheMarkupTheThemeStyles(
        string $marker,
        string $classes,
        string $icon,
        string $title,
    ): void {
        $html = $this->convert("> [!{$marker}]\n> Body text.\n");

        static::assertStringContainsString("<div class=\"{$classes}", $html);
        static::assertStringContainsString('role="note"', $html);
        static::assertStringContainsString("<i class=\"bi {$icon}\" aria-hidden=\"true\"></i> {$title}</p>", $html);
        static::assertStringContainsString('<p class="admonition-title">', $html);
        static::assertStringContainsString('<p>Body text.</p>', $html);
        static::assertStringNotContainsString('[!', $html);
        static::assertStringNotContainsString('<blockquote>', $html);
    }

    private function convert(string $markdown): string
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AdmonitionExtension());

        return (string) new MarkdownConverter($environment)->convert($markdown);
    }
}
