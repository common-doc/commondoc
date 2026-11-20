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

use CommonDoc\Container\MarkdownConverterFactory;
use CommonDoc\Input\Source;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use Laminas\ServiceManager\ServiceManager;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The converter's two modes, told apart by what they do to a link.
 *
 * A stub container cannot answer `build()`, and `build()` is the whole point of
 * the scan mode, so these tests use a real `ServiceManager`. The assertions are
 * behavioural rather than about identity: without a scan a converter has no
 * rewriter, so `[intro](intro.md)` keeps the target exactly as written, and with
 * a scan it becomes the published path. Identity would prove less, because a
 * fresh environment that happened to be empty would pass it too.
 */
#[CoversClass(MarkdownConverterFactory::class)]
#[CoversMethod(MarkdownConverterFactory::class, '__invoke')]
final class MarkdownConverterFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheConverterAroundTheEnvironmentFromTheContainer(): void
    {
        $converter = new MarkdownConverterFactory()($this->container(), MarkdownConverter::class);

        static::assertInstanceOf(MarkdownConverter::class, $converter);
    }

    #[Test]
    public function ignoresOptionsThatAreNotAScan(): void
    {
        // The options argument is open to any caller, so an unrecognised shape is
        // a plain request rather than an error.
        $converter = new MarkdownConverterFactory()(
            $this->container(),
            MarkdownConverter::class,
            ['unrelated' => true],
        );

        $html = (string) $converter->convert('[intro](intro.md)');

        static::assertStringNotContainsString('/guide/intro/', $html);
        static::assertStringContainsString('intro.md', $html);
    }

    #[Test]
    public function keepsTheRewriterWhenTheScanCarriesOrigins(): void
    {
        // `Type\shape` refuses an unknown key, so validating the whole options array
        // with it made a third key look like "no scan" and silently dropped the
        // rewriter: every link then shipped exactly as written and the build
        // reported success. This pins that a scan with origins still rewrites.
        $converter = new MarkdownConverterFactory()(
            $this->container(),
            MarkdownConverter::class,
            [
                'documents' => ['guide/intro.md'],
                'assets'    => [],
                'origins'   => ['/' => new Source('https://github.com/example/repo', 'abc123')],
            ],
        );

        static::assertStringContainsString('/guide/intro/', (string) $converter->convert('[intro](intro.md)'));
    }

    #[Test]
    public function refusesALinkTheScanDoesNotContain(): void
    {
        // The source, through a converter built for a build that never
        // discovered the target. A shared converter would have answered with
        // another list; a built one cannot.
        $converter = new MarkdownConverterFactory()(
            $this->container(),
            MarkdownConverter::class,
            ['documents' => ['guide/setup.md'], 'assets' => []],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('which resolves to "guide/intro.md"');

        $converter->convert('[intro](intro.md)');
    }

    #[Test]
    public function rewritesALinkOnlyWhenItIsGivenAScan(): void
    {
        $plain = new MarkdownConverterFactory()($this->container(), MarkdownConverter::class);
        $built = new MarkdownConverterFactory()(
            $this->container(),
            MarkdownConverter::class,
            ['documents' => ['guide/intro.md'], 'assets' => []],
        );

        static::assertStringNotContainsString('/guide/intro/', (string) $plain->convert('[intro](intro.md)'));
        static::assertStringContainsString('/guide/intro/', (string) $built->convert('[intro](intro.md)'));
    }

    /**
     * A service manager with the smallest configuration that resolves an
     * environment and a document root.
     *
     * The environment is not shared, so `get()` and `build()` behave as they do
     * in the application: one object each time, never the same one twice, and
     * neither carrying a listener until a scan asks for one.
     */
    private function container(): ServiceManager
    {
        // The page is what a relative target resolves against, and it reaches the
        // document on the parsed event, exactly as in the application.
        $context = new PageContext();
        $context->set(new Page(
            label       : 'Setup',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'guide/setup.md',
            url         : '/guide/setup/',
            versionLabel: '1.0',
        ));

        return new ServiceManager([
            'factories' => [
                EnvironmentInterface::class => static function () use ($context): Environment {
                    $environment = new Environment();
                    $environment->addExtension(new CommonMarkCoreExtension());
                    $environment->addEventListener(
                        DocumentParsedEvent::class,
                        new PageContextListener($context),
                    );

                    return $environment;
                },
                DocumentRoot::class         => static fn(): DocumentRoot => new DocumentRoot('/'),
            ],
        ]);
    }
}
