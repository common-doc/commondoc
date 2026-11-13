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

use Closure;
use CommonDoc\View\Page;
use Laminas\View\Model\ModelInterface;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function iterator_count;

#[CoversClass(Page::class)]
#[CoversMethod(Page::class, 'getTemplate')]
#[CoversMethod(Page::class, 'getVariables')]
#[CoversMethod(Page::class, 'getVariable')]
#[CoversMethod(Page::class, 'getChildren')]
#[CoversMethod(Page::class, 'hasChildren')]
#[CoversMethod(Page::class, 'captureTo')]
#[CoversMethod(Page::class, 'isAppend')]
#[CoversMethod(Page::class, 'terminate')]
#[CoversMethod(Page::class, 'count')]
#[CoversMethod(Page::class, 'getIterator')]
#[CoversMethod(Page::class, 'setVariable')]
#[CoversMethod(Page::class, 'setVariables')]
#[CoversMethod(Page::class, 'setTemplate')]
#[CoversMethod(Page::class, 'addChild')]
#[CoversMethod(Page::class, 'setCaptureTo')]
#[CoversMethod(Page::class, 'setTerminal')]
#[CoversMethod(Page::class, 'setAppend')]
final class PageTest extends TestCase
{
    /**
     * @return array<string, array{Closure(Page): mixed}>
     */
    public static function mutators(): array
    {
        return [
            'setVariable'  => [static fn(Page $page): mixed => $page->setVariable('extra', 'value')],
            'setVariables' => [static fn(Page $page): mixed => $page->setVariables(['extra' => 'value'])],
            'setTemplate'  => [static fn(Page $page): mixed => $page->setTemplate('other::template')],
            'addChild'     => [static fn(Page $page): mixed => $page->addChild(self::leaf())],
            'setCaptureTo' => [static fn(Page $page): mixed => $page->setCaptureTo('other')],
            'setTerminal'  => [static fn(Page $page): mixed => $page->setTerminal(true)],
            'setAppend'    => [static fn(Page $page): mixed => $page->setAppend(append: true)],
        ];
    }

    private static function leaf(string $template = 'commondoc::page'): Page
    {
        return new Page(
            label       : 'Reference page',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'docs/reference/page.md',
            url         : '/reference/page',
            versionLabel: '1.0',
            template    : $template,
        );
    }

    #[Test]
    public function countsAndIteratesTheSameChildren(): void
    {
        $children = [self::leaf(), self::leaf()];
        $page     = new Page(
            label       : 'Parent',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'docs/parent.md',
            url         : '/parent',
            versionLabel: '1.0',
            children    : $children,
        );

        static::assertTrue($page->hasChildren());
        static::assertSame($children, $page->getChildren());
        static::assertSame(2, $page->count());
        static::assertSame($page->count(), iterator_count($page->getIterator()));
    }

    #[Test]
    public function neverProjectsTheLayoutReservedContentVariable(): void
    {
        static::assertArrayNotHasKey('content', self::leaf()->getVariables());
    }

    #[Test]
    public function projectsThePageFieldsIntoTheVariables(): void
    {
        static::assertSame(
            [
                'label'        => 'Reference page',
                'url'          => '/reference/page',
                'projectSlug'  => 'commondoc',
                'projectName'  => 'CommonDoc',
                'versionLabel' => '1.0',
                'sourcePath'   => 'docs/reference/page.md',
                'captureTo'    => 'page',
                'append'       => false,
                'terminal'     => false,
                'children'     => [],
            ],
            self::leaf()->getVariables(),
        );
    }

    #[Test]
    public function readsASingleVariableFromTheProjection(): void
    {
        $page = self::leaf();

        static::assertSame('Reference page', $page->getVariable('label'));
        static::assertSame('/reference/page', $page->getVariable('url'));
    }

    /**
     * @param Closure(Page): mixed $mutate
     */
    #[Test]
    #[DataProvider('mutators')]
    public function rejectsEveryMutation(Closure $mutate): void
    {
        $page = self::leaf();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot modify page "Reference page": a Page is immutable.');

        $mutate($page);
    }

    #[Test]
    public function reportsNoChildrenForALeafPage(): void
    {
        $page = self::leaf();

        static::assertFalse($page->hasChildren());
        static::assertSame([], $page->getChildren());
        static::assertSame(0, $page->count());
        static::assertSame(0, iterator_count($page->getIterator()));
    }

    #[Test]
    public function reportsTheCaptureTargetAndTheFlags(): void
    {
        $page = new Page(
            label       : 'Parent',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'docs/parent.md',
            url         : '/parent',
            versionLabel: '1.0',
            append      : true,
            captureTo   : 'sidebar',
            terminal    : true,
        );

        static::assertSame('sidebar', $page->captureTo());
        static::assertTrue($page->isAppend());
        static::assertTrue($page->terminate());
    }

    #[Test]
    public function returnsTheConfiguredTemplateName(): void
    {
        static::assertSame('commondoc::page', self::leaf()->getTemplate());
        static::assertSame('site::document', self::leaf(template: 'site::document')->getTemplate());
    }

    #[Test]
    public function returnsTheGivenDefaultForAnUnknownVariable(): void
    {
        $page = self::leaf();

        static::assertNull($page->getVariable('missing'));
        static::assertSame('fallback', $page->getVariable('missing', 'fallback'));
    }

    #[Test]
    public function satisfiesTheViewModelContract(): void
    {
        static::assertInstanceOf(ModelInterface::class, self::leaf());
    }
}
