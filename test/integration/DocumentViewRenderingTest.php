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

namespace CommonDocTestIntegration;

use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Exception\RenderingFailedException;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Renderer\Block\DocumentRenderer;
use League\CommonMark\Renderer\NodeRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function bin2hex;
use function file_put_contents;
use function glob;
use function is_array;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function strpos;
use function sys_get_temp_dir;
use function unlink;

/**
 * Renders a document through the whole seam: the real container, the real
 * CommonMark environment and the real Laminas view renderer.
 *
 * Only an integration test can prove the three claims that matter here, because
 * each one is a claim about composition: a site replaces the body by
 * configuration alone, the packaged body renders inside the layout, and a body
 * name that resolves to nothing surfaces as the view renderer's own exception.
 */
#[CoversClass(DocumentViewRenderer::class)]
#[CoversMethod(DocumentViewRenderer::class, 'render')]
final class DocumentViewRenderingTest extends TestCase
{
    private string $directory;

    #[Test]
    public function keepsTheLibraryRendererAndConverterUnsubclassed(): void
    {
        $container = $this->container($this->config());

        static::assertSame(MarkdownConverter::class, $container->get(ConverterInterface::class)::class);

        /** @var EnvironmentInterface $environment */
        $environment = $container->get(EnvironmentInterface::class);

        $renderers = array_map(
            static fn(NodeRendererInterface $renderer): string => $renderer::class,
            [...$environment->getRenderersForClass(Document::class)],
        );

        static::assertSame(DocumentViewRenderer::class, $renderers[0]);
        static::assertContains(DocumentRenderer::class, $renderers);
    }

    #[Test]
    public function loadsMermaidOnlyForAPageThatHasADiagram(): void
    {
        $output = $this->renderDocument($this->config(), markdown: "```mermaid\ngraph TD; A-->B;\n```\n");

        static::assertStringContainsString('mermaid.min.js', $output);
        // A diagram is not code to colour, so a page of only diagrams loads no highlighter.
        static::assertStringNotContainsString('highlight.min.js', $output);
    }

    #[Test]
    public function loadsNeitherForAPageWithNeither(): void
    {
        $output = $this->renderDocument($this->config(), markdown: "Plain prose.\n\n```\nno language\n```\n");

        static::assertStringNotContainsString('highlight.min.js', $output);
        static::assertStringNotContainsString('mermaid.min.js', $output);
    }

    #[Test]
    public function loadsTheHighlighterOnlyForAPageThatHasCode(): void
    {
        $output = $this->renderDocument($this->config(), markdown: "```php\necho 1;\n```\n");

        static::assertStringContainsString('highlight.min.js', $output);
        static::assertStringContainsString('id="hljs-light"', $output);
        static::assertStringContainsString('id="hljs-dark"', $output);
        static::assertStringNotContainsString('mermaid.min.js', $output);
    }

    #[Test]
    public function raisesTheTemplateNotFoundFailureWhenTheBodyNameResolvesToNothing(): void
    {
        $this->expectException(RenderingFailedException::class);
        $this->expectExceptionMessage('commondoc::document');

        $this->renderDocument($this->configWithoutABodyTemplate());
    }

    #[Test]
    public function rendersADocumentWithNoPagePresent(): void
    {
        $container = $this->container($this->config());

        /** @var PageContext $context */
        $context = $container->get(PageContext::class);

        static::assertNull($context->current());

        /** @var ConverterInterface $converter */
        $converter = $container->get(ConverterInterface::class);

        $output = (string) $converter->convert("# Heading\n\nBody paragraph.");

        static::assertStringContainsString('<!DOCTYPE html>', $output);
        static::assertStringContainsString('<main class="document">', $output);
        static::assertStringContainsString('<p>Body paragraph.</p>', $output);
        static::assertStringContainsString('<title></title>', $output);
    }

    #[Test]
    public function rendersAHelperContributedRegionInsideTheBody(): void
    {
        $body = "{$this->directory}/helper.phtml";
        file_put_contents($body, data: <<<'PHTML'
            <?php $this->headLink()->appendStylesheet('/assets/helper.css'); ?>
            <main class="document">
                <?= $this->headLink() ?>
                <?= $documentBody ?>
            </main>
            PHTML);

        $output = $this->renderDocument($this->configMappedTo($body));

        static::assertStringContainsString('helper.css', $output);
        static::assertStringContainsString('rel="stylesheet"', $output);
        static::assertStringContainsString('<p>Body paragraph.</p>', $output);
    }

    #[Test]
    public function rendersANodeContributedByAnInstalledExtension(): void
    {
        $output = $this->renderDocument(
            $this->config(),
            markdown: "| a | b |\n| - | - |\n| 1 | 2 |",
        );

        static::assertStringContainsString('<table>', $output);
        static::assertStringContainsString('<td>1</td>', $output);
    }

    #[Test]
    public function rendersEachDocumentWithOnlyItsOwnContent(): void
    {
        $container = $this->container($this->config());

        /** @var ConverterInterface $converter */
        $converter = $container->get(ConverterInterface::class);

        /** @var PageContext $context */
        $context = $container->get(PageContext::class);

        $seen = [];

        for ($index = 1; $index <= 10; $index++) {
            $label = sprintf('Page %02d', $index);

            $context->set(new Page(
                label       : $label,
                projectName : 'CommonDoc',
                projectSlug : 'commondoc',
                sourcePath  : "docs/page-{$index}.md",
                url         : "/page-{$index}",
                versionLabel: '1.0',
                routeName   : '/page',
            ));

            $output = (string) $converter->convert("## Section {$index}\n\nBody {$index}.");
            $context->clear();

            static::assertStringContainsString("<title>{$label}</title>", $output);
            static::assertStringContainsString("Section {$index}", $output);
            static::assertStringContainsString("Body {$index}.", $output);

            foreach ($seen as $earlier) {
                static::assertStringNotContainsString($earlier, $output);
            }

            $seen[] = $label;
        }
    }

    #[Test]
    public function rendersNoSwitcherForAPageWithoutVersions(): void
    {
        static::assertStringNotContainsString('data-dropdown="versions"', $this->renderDocument($this->config()));
    }

    #[Test]
    public function rendersTheBodyTemplateTheSiteConfigures(): void
    {
        $override = "{$this->directory}/override.phtml";
        file_put_contents($override, data: '<div id="site-body"><?= $documentBody ?></div>');

        $output = $this->renderDocument($this->configMappedTo($override));

        static::assertStringContainsString('<div id="site-body">', $output);
        static::assertStringContainsString('<p>Body paragraph.</p>', $output);
        static::assertStringNotContainsString('<main class="document">', $output);
    }

    #[Test]
    public function rendersTheVersionSwitcherForAPageThatHasVersions(): void
    {
        $container = $this->container($this->config());

        /** @var ConverterInterface $converter */
        $converter = $container->get(ConverterInterface::class);

        /** @var PageContext $context */
        $context = $container->get(PageContext::class);

        $context->set(new Page(
            label       : 'Guide',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'bus/v1/index.md',
            url         : '/bus/v1/',
            versionLabel: '1.0',
            routeName   : '/bus/v1/',
            versions    : [
                ['label' => 'v2 (latest)', 'url' => '/bus/v2/', 'current' => false],
                ['label' => 'v1', 'url' => '/bus/v1/', 'current' => true],
            ],
        ));

        $output = (string) $converter->convert("# Guide\n");
        $context->clear();

        // The navbar renders after the document body, from the page the context still holds.
        static::assertStringContainsString('data-dropdown="versions"', $output);
        static::assertStringContainsString(
            '<a class="dropdown-item" href="&#x2F;bus&#x2F;v2&#x2F;">v2 (latest)</a>',
            $output,
        );
        static::assertStringContainsString(
            '<a class="dropdown-item active" href="&#x2F;bus&#x2F;v1&#x2F;" aria-current="page">v1</a>',
            $output,
        );
        static::assertStringContainsString('>v1</button>', $output);
    }

    #[Test]
    public function setsTheThemeBeforeTheStylesheetAndSwitchesItWithoutReloading(): void
    {
        $output = $this->renderDocument($this->config());

        $script     = strpos($output, needle: "setAttribute('data-bs-theme'");
        $stylesheet = strpos($output, needle: 'bootstrap.min.css');

        // The attribute is written before the stylesheet is requested, which is what keeps
        // the first paint in the reader's theme, and the toggle only rewrites it.
        static::assertIsInt($script);
        static::assertIsInt($stylesheet);
        static::assertLessThan($stylesheet, $script);
        static::assertStringContainsString('data-theme-toggle', $output);
        static::assertStringContainsString('data-search', $output);
        static::assertStringContainsString('search-index.json', $output);
        static::assertStringContainsString('localStorage.setItem(\'commondoc-theme\'', $output);
    }

    #[Test]
    public function wrapsThePackagedBodyInTheLayout(): void
    {
        $output = $this->renderDocument($this->config());

        static::assertStringContainsString('<!DOCTYPE html>', $output);
        static::assertStringContainsString('<main class="document">', $output);
        static::assertStringContainsString('<p>Body paragraph.</p>', $output);
        static::assertStringContainsString('<title>', $output);
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/commondoc-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach ([...glob("{$this->directory}/*.phtml")] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $config = require __DIR__ . '/../../config/config.php';

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function configMappedTo(string $path): array
    {
        $config = $this->config();

        /** @var mixed $templates */
        $templates = $config['templates'] ?? [];

        /** @var array{map: array<string, string>, layout: string} $templates */
        $templates['map']['commondoc::document'] = $path;
        $config['templates']                     = $templates;

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function configWithoutABodyTemplate(): array
    {
        $config = $this->config();

        /** @var mixed $templates */
        $templates = $config['templates'] ?? [];

        /** @var array{map: array<string, string>, layout: string} $templates */
        unset($templates['map']['commondoc::document']);
        $config['templates'] = $templates;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function container(array $config): ServiceManager
    {
        /** @var mixed $declared */
        $declared = $config['dependencies'] ?? [];

        /** @var array<string, mixed> $dependencies */
        $dependencies = is_array($declared) ? $declared : [];

        $dependencies['services']['config'] = $config;

        return new ServiceManager($dependencies);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function renderDocument(array $config, string $markdown = "# Heading\n\nBody paragraph."): string
    {
        $container = $this->container($config);

        /** @var ConverterInterface $converter */
        $converter = $container->get(ConverterInterface::class);

        return (string) $converter->convert($markdown);
    }
}
