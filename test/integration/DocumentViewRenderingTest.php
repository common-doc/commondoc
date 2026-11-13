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
            "| a | b |\n| - | - |\n| 1 | 2 |",
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
