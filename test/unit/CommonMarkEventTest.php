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

use CommonDoc\CommonMarkEvent;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The case values name events CommonMark actually dispatches.
 *
 * The check is behavioural rather than only a comparison. Comparing a case against
 * `DocumentParsedEvent::class` would pass even if CommonMark stopped dispatching the
 * event, because both sides would still name the same class. Firing a real
 * conversion through a real environment proves the identifier is one CommonMark
 * actually sends, which is the property the registration depends on:
 * `getListenersForEvent()` matches with `is_a()`, so a value nothing dispatches
 * means a listener that never fires and a build that finishes with no page identity
 * and no rewritten links.
 */
#[CoversClass(CommonMarkEvent::class)]
final class CommonMarkEventTest extends TestCase
{
    #[Test]
    public function holdsOnlyTheEventsThisPackageListensFor(): void
    {
        // Not CommonMark's four events. `DocumentPreParsedEvent`,
        // `DocumentPreRenderEvent` and `DocumentRenderedEvent` exist and are not
        // here, because nothing in this package listens for them. A case is added
        // when a listener is, never in advance.
        static::assertCount(1, CommonMarkEvent::cases());
    }

    #[Test]
    public function namesTheClassCommonMarkDefines(): void
    {
        // The value is a reference rather than a written-out string, so a rename in
        // the library breaks the enum instead of leaving a name nothing dispatches.
        static::assertSame(DocumentParsedEvent::class, CommonMarkEvent::DocumentParsed->value);
    }

    #[Test]
    public function namesTheEventFiredWhenADocumentIsParsed(): void
    {
        $fired = 0;

        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addEventListener(
            CommonMarkEvent::DocumentParsed->value,
            static function (DocumentParsedEvent $event) use (&$fired): void {
                $fired++;
            },
        );

        new MarkdownConverter($environment)->convert("# Home\n");

        // Once per document, which is what makes this a per-document hook rather
        // than a per-build one.
        static::assertSame(1, $fired);
    }
}
