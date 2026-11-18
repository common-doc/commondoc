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

use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\LinkRewriter;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Node\Block\Document;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The rewriter is still a skeleton: its only behaviour is refusing. These tests
 * pin that refusal, including the two values it reports, so the place it will
 * eventually do work is a loud failure rather than a silent no-op that publishes
 * a site with broken links.
 */
#[CoversClass(LinkRewriter::class)]
#[CoversMethod(LinkRewriter::class, '__construct')]
#[CoversMethod(LinkRewriter::class, '__invoke')]
final class LinkRewriterTest extends TestCase
{
    private static function event(): DocumentParsedEvent
    {
        return new DocumentParsedEvent(new Document());
    }

    #[Test]
    public function refusesUntilTheTreeWalkExists(): void
    {
        $rewriter = new LinkRewriter(new DocumentRoot('/repo/'), []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'is not implemented; 0 documents under "/repo/" are published with their links unchanged.',
        );

        $rewriter(self::event());
    }

    #[Test]
    public function reportsHowManyDocumentsItWasGiven(): void
    {
        $rewriter = new LinkRewriter(
            new DocumentRoot('/'),
            ['guide/setup.md', 'index.md'],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('2 documents under "/"');

        $rewriter(self::event());
    }
}
