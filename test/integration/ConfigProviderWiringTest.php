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

use CommonDoc\ConfigProvider;
use CommonDoc\Container\DocumentViewRendererFactory;
use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use JsonException;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Guards the wiring contract between composer.json, the aggregated config and
 * this package's namespace.
 *
 * Renaming the namespace but missing `extra.laminas.config-provider` breaks every
 * consumer silently, so the two are asserted to agree. The remaining cases boot
 * the real container, because a provider entry that is never aggregated is only
 * visible that way.
 */
#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, 'getDependencies')]
#[CoversMethod(ConfigProvider::class, 'getViewConfig')]
final class ConfigProviderWiringTest extends TestCase
{
    /**
     * @throws JsonException
     */
    #[Test]
    public function composerDeclaresThisPackageAsItsConfigProvider(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/../../composer.json');

        /** @var array{extra: array{laminas: array{'config-provider': string}}} $composer */
        $composer = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        static::assertSame(
            ConfigProvider::class,
            $composer['extra']['laminas']['config-provider'],
        );
    }

    #[Test]
    public function publishesTheTemplateMapAndTheLayout(): void
    {
        /** @var array{templates: array{map: array<string, string>, layout: string}} $config */
        $config = $this->container()->get('config');

        $provider = new ConfigProvider()->getViewConfig();

        static::assertSame($provider['map'], $config['templates']['map']);
        static::assertSame($provider['layout'], $config['templates']['layout']);
    }

    #[Test]
    public function resolvesTheDocumentViewRendererFromItsFactory(): void
    {
        $dependencies = new ConfigProvider()->getDependencies();

        static::assertSame(
            DocumentViewRendererFactory::class,
            $dependencies['factories'][DocumentViewRenderer::class],
        );
        static::assertInstanceOf(
            DocumentViewRenderer::class,
            $this->container()->get(DocumentViewRenderer::class),
        );
    }

    #[Test]
    public function resolvesThePageContextAndItsListener(): void
    {
        $container = $this->container();

        static::assertInstanceOf(PageContext::class, $container->get(PageContext::class));
        static::assertInstanceOf(PageContextListener::class, $container->get(PageContextListener::class));
    }

    private function container(): ServiceManager
    {
        return require __DIR__ . '/../../config/container.php';
    }
}
