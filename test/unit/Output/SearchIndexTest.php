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

namespace CommonDocTest\Output;

use CommonDoc\Output\SearchIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function substr_count;

#[CoversClass(SearchIndex::class)]
#[CoversMethod(SearchIndex::class, 'entry')]
final class SearchIndexTest extends TestCase
{
    private const string PAGE = <<<'HTML'
        <html><body>
        <nav class="site-nav"><a href="/other/">Other Component</a></nav>
        <main class="document">
        <ul class="table-of-contents"><li><a href="#setup">Setup</a></li></ul>
        <h1 id="setup"><a href="#setup" class="heading-permalink" aria-hidden="true">&para;</a>Setup &amp; install</h1>
        <p>Run <code>composer</code> first.</p><h2 id="usage">Usage</h2><p>Then use it.</p>
        </main>
        <nav class="version-switcher"><a href="/x/">v2</a></nav>
        </body></html>
        HTML;

    #[Test]
    public function isNothingForAPageWithNoDocumentBody(): void
    {
        static::assertNull(new SearchIndex()->entry('<html><body>Redirecting</body></html>', url: '/r/', section: ''));
    }

    #[Test]
    public function leavesOutTheSidebarTheSwitcherAndTheTableOfContents(): void
    {
        $entry = new SearchIndex()->entry(self::PAGE, url: '/x/', section: '');

        static::assertNotNull($entry);
        static::assertStringNotContainsString('Other Component', $entry['text']);
        static::assertStringNotContainsString('v2', $entry['text']);
        static::assertStringNotContainsString('¶', $entry['text']);
        // The contents list repeats the heading, which would score it twice.
        static::assertSame(1, substr_count($entry['text'], needle: 'Setup'));
    }

    #[Test]
    public function readsTheTitleTheHeadingsAndTheVisibleTextOfTheBody(): void
    {
        $entry = new SearchIndex()->entry(self::PAGE, url: '/guide/setup/', section: 'comp/v1');

        static::assertNotNull($entry);
        static::assertSame('/guide/setup/', $entry['url']);
        static::assertSame('comp/v1', $entry['section']);
        static::assertSame('Setup & install', $entry['title']);
        static::assertSame(
            [
                ['id' => 'setup', 'title' => 'Setup & install', 'level' => 1],
                ['id' => 'usage', 'title' => 'Usage', 'level' => 2],
            ],
            $entry['headings'],
        );
        static::assertSame('Setup & install Run composer first. Usage Then use it.', $entry['text']);
    }

    #[Test]
    public function skipsAHeadingWithNoText(): void
    {
        $html = '<main class="document"><h1 id="a"></h1><h2 id="b">Real</h2></main>';

        $entry = new SearchIndex()->entry($html, url: '/p/', section: '');

        static::assertSame([['id' => 'b', 'title' => 'Real', 'level' => 2]], $entry['headings'] ?? null);
    }

    #[Test]
    public function titlesAPageWithoutAFirstLevelHeadingByItsAddress(): void
    {
        $html = '<main class="document"><h2 id="a">Only a second level</h2></main>';

        static::assertSame('/page/', new SearchIndex()->entry($html, url: '/page/', section: '')['title'] ?? null);
    }
}
