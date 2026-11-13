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
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Exception\RenderingFailedException;
use League\CommonMark\ConverterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function glob;
use function is_array;
use function mkdir;
use function random_bytes;
use function rmdir;
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
    public function raisesTheTemplateNotFoundFailureWhenTheBodyNameResolvesToNothing(): void
    {
        $this->expectException(RenderingFailedException::class);
        $this->expectExceptionMessage('commondoc::document');

        $this->renderDocument($this->configWithoutABodyTemplate());
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
    private function renderDocument(array $config): string
    {
        $container = $this->container($config);

        /** @var ConverterInterface $converter */
        $converter = $container->get(ConverterInterface::class);

        return (string) $converter->convert("# Heading\n\nBody paragraph.");
    }
}
