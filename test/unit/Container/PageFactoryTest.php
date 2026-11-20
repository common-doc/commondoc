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

namespace CommonDocTest\Container;

use CommonDoc\Container\PageFactory;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\SiteConfig;
use CommonDoc\View\Page;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type\Exception\CoercionException;
use Psr\Container\ContainerInterface;

/**
 * One page per document, built from options rather than from a captured callable.
 *
 * The caller is what varies per document, so the caller supplies the local path
 * and the label. Everything a deployment shares comes from config, and the
 * path is derived, because a page that carried its own URL could disagree
 * with the file the write pass produces for the same document.
 */
#[CoversClass(PageFactory::class)]
#[CoversMethod(PageFactory::class, '__invoke')]
final class PageFactoryTest extends TestCase
{
    /**
     * @param array{projectName?: string, projectSlug?: string, versionLabel?: string} $identity
     */
    private static function container(array $identity, DocumentRoot $root): ContainerInterface
    {
        $siteConfig = new SiteConfig(
            documentRoot: $root,
            projectName : $identity['projectName'] ?? '',
            projectSlug : $identity['projectSlug'] ?? '',
            versionLabel: $identity['versionLabel'] ?? '',
        );

        $container = self::createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    SiteConfig::class   => $siteConfig,
                    DocumentRoot::class => $root,
                    default             => null,
                },
            );

        return $container;
    }

    /**
     * Build one page the way a caller does, through the factory's options.
     *
     * @param array<array-key, mixed>                                                  $options
     * @param array{projectName?: string, projectSlug?: string, versionLabel?: string} $identity
     */
    private static function page(
        array $options,
        array $identity = [],
        ?DocumentRoot $root = null,
    ): Page {
        $subject = new PageFactory()(
            self::container($identity, $root ?? new DocumentRoot('/repo/')),
            Page::class,
            $options,
        );

        static::assertInstanceOf(Page::class, $subject);

        return $subject;
    }

    #[Test]
    public function acceptsChildrenWhenTheyArePages(): void
    {
        $child = self::page(
            ['localPath' => 'guide/setup.md', 'label' => 'Setup'],
        );
        $parent = self::page(
            ['localPath' => 'guide/index.md', 'label' => 'Guide', 'children' => [$child]],
        );

        static::assertSame([$child], $parent->getChildren());
        static::assertTrue($parent->hasChildren());
    }

    #[Test]
    public function capturesTheDeploymentIdentityForEveryPage(): void
    {
        $identity = [
            'projectName'  => 'CommonDoc',
            'projectSlug'  => 'commondoc',
            'versionLabel' => '1.0',
        ];

        $first  = self::page(['localPath' => 'index.md', 'label' => 'Home'], $identity);
        $second = self::page(['localPath' => 'guide/setup.md', 'label' => 'Setup'], $identity);

        static::assertSame('CommonDoc', $first->projectName);
        static::assertSame('commondoc', $first->projectSlug);
        static::assertSame('1.0', $first->versionLabel);

        // The identity is shared; only what differs per document is not.
        static::assertSame($first->projectName, $second->projectName);
        static::assertSame($first->projectSlug, $second->projectSlug);
        static::assertSame($first->versionLabel, $second->versionLabel);
        static::assertNotSame($first->url, $second->url);
    }

    #[Test]
    public function derivesThePublishedPathRatherThanAcceptingOne(): void
    {
        $page = self::page(['localPath' => 'guide/setup.md', 'label' => 'Setup']);

        static::assertSame('guide/setup.md', $page->sourcePath);
        static::assertSame('/repo/guide/setup/', $page->url);
    }

    #[Test]
    public function leavesTheIdentityBlankWhenNoSectionIsConfigured(): void
    {
        $page = self::page(['localPath' => 'index.md', 'label' => 'Home']);

        static::assertSame('', $page->projectName);
        static::assertSame('', $page->projectSlug);
        static::assertSame('', $page->versionLabel);
    }

    #[Test]
    public function leavesTheIdentityBlankWhenTheSiteConfigCarriesNone(): void
    {
        // A deployment that states no identity still yields a usable page: a page
        // with no project name is cosmetic, a build that cannot start is not.
        $page = self::page(['localPath' => 'index.md', 'label' => 'Home'], [], new DocumentRoot('/'));

        static::assertSame('', $page->projectName);
        static::assertSame('/', $page->url);
    }

    #[Test]
    public function refusesAChildThatIsNotAPage(): void
    {
        $this->expectException(CoercionException::class);

        self::page([
            'localPath' => 'guide/index.md',
            'label'     => 'Guide',
            'children'  => ['not a page'],
        ]);
    }

    #[Test]
    public function refusesALocalPathThatIsNotADocument(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::page(['localPath' => 'guide/setup.html', 'label' => 'Setup']);
    }

    #[Test]
    public function refusesOptionsThatDoNotNameADocument(): void
    {
        // Without a local path there is no path to derive, so this is a
        // construction failure with a message rather than a page with an empty
        // source path that the write pass would then refuse.
        $this->expectException(InvalidArgumentException::class);

        self::page(['label' => 'Setup']);
    }
}
