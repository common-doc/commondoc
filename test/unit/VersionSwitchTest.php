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

use CommonDoc\View\Dropdown;
use CommonDoc\View\Helper\VersionSwitch;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use Laminas\Escaper\Escaper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The versions of the component being read, as a navbar dropdown.
 *
 * The page is read from the context, which the build keeps populated while the layout
 * renders, so the fixture sets it the way the build does rather than passing it in.
 */
#[CoversClass(VersionSwitch::class)]
#[CoversMethod(VersionSwitch::class, '__construct')]
#[CoversMethod(VersionSwitch::class, '__invoke')]
final class VersionSwitchTest extends TestCase
{
    #[Test]
    public function disablesTheMenuWhenThereIsOnlyOneVersion(): void
    {
        $html = $this->switch(versions: [
            ['label' => 'v1', 'url' => '/bus/v1/', 'current' => true],
        ])->__invoke();

        static::assertStringContainsString('disabled>v1</button>', $html);
        static::assertStringNotContainsString('dropdown-menu', $html);
    }

    #[Test]
    public function listsEveryVersionAndMarksTheCurrentOne(): void
    {
        $html = $this->switch(versions: $this->twoVersions())->__invoke();

        static::assertStringContainsString('href="&#x2F;bus&#x2F;v2&#x2F;">v2 (latest)</a>', $html);
        static::assertStringContainsString(
            'href="&#x2F;bus&#x2F;v1&#x2F;" aria-current="page">v1</a>',
            $html,
        );
    }

    #[Test]
    public function namesTheCurrentVersionOnTheButton(): void
    {
        static::assertStringContainsString(
            '>v1</button>',
            $this->switch(versions: $this->twoVersions())->__invoke(),
        );
    }

    #[Test]
    public function rendersNothingForAPageWithoutVersions(): void
    {
        static::assertSame('', $this->switch(versions: [])->__invoke());
    }

    #[Test]
    public function rendersNothingOutsideABuild(): void
    {
        static::assertSame('', $this->subject(new PageContext())->__invoke());
    }

    private function subject(PageContext $context): VersionSwitch
    {
        return new VersionSwitch(
            context : $context,
            dropdown: new Dropdown(new Escaper()),
        );
    }

    /**
     * @param list<array{label: string, url: string, current: bool}> $versions
     */
    private function switch(array $versions): VersionSwitch
    {
        $context = new PageContext();
        $context->set(new Page(
            label       : 'Guide',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            sourcePath  : 'bus/v1/index.md',
            url         : '/bus/v1/',
            versionLabel: '1.0',
            routeName   : '/bus/v1/',
            versions    : $versions,
        ));

        return $this->subject($context);
    }

    /**
     * @return list<array{label: string, url: string, current: bool}>
     */
    private function twoVersions(): array
    {
        return [
            ['label' => 'v2 (latest)', 'url' => '/bus/v2/', 'current' => false],
            ['label' => 'v1', 'url' => '/bus/v1/', 'current' => true],
        ];
    }
}
