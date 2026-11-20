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

namespace CommonDocTest\View;

use CommonDoc\View\HeadingListener;
use CommonDoc\View\PageContext;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Inline\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A document's headings, collected as link targets.
 *
 * The environment here is real for the parts that matter: the same core and permalink
 * extensions, and the same empty prefixes the shipped config uses. A collection that
 * agreed with a stub while disagreeing with the renderer would be the one failure this has
 * to prevent, since the whole point is that a fragment reaches its heading.
 */
#[CoversClass(HeadingListener::class)]
#[CoversMethod(HeadingListener::class, '__invoke')]
final class HeadingListenerTest extends TestCase
{
    #[Test]
    public function collectsEveryHeadingWithTheIdTheRendererEmits(): void
    {
        $context = $this->convert("# Paths\n\ntext\n\n## The index rule\n\n### Deep\n");

        static::assertSame(['paths', 'the-index-rule', 'deep'], $this->fragments($context));
        static::assertSame([1, 2, 3], $this->levels($context));
    }

    #[Test]
    public function collectsNothingFromADocumentWithNoHeadings(): void
    {
        static::assertSame([], $this->convert("just a paragraph\n")->sections());
    }

    #[Test]
    public function keepsHeadingsInDocumentOrder(): void
    {
        $context = $this->convert("## First\n\n### Nested\n\n## Second\n");

        static::assertSame(['first', 'nested', 'second'], $this->fragments($context));
    }

    #[Test]
    public function labelsAHeadingWithoutTheRawMarkupItContains(): void
    {
        // Raw inline markup is not text a reader sees, and the permalink extension excludes
        // it for the same reason. Including it would label the link `foo <span>bar</span>`.
        $context = $this->convert("## foo <span>bar</span>\n");

        static::assertSame(['foo-bar'], $this->fragments($context));
        static::assertSame(['foo bar'], $this->labels($context));
    }

    #[Test]
    public function labelsAHeadingWithTheTextItShows(): void
    {
        // Emphasis, a code span and a link all reduce to their words, which is the same
        // reduction the permalink extension applies to derive the slug. Anything less and a
        // heading whose slug names `set()` would be labelled without it.
        $context = $this->convert("## The *index* rule and `set()` plus [a link](x.md)\n");

        static::assertSame(['the-index-rule-and-set-plus-a-link'], $this->fragments($context));
        static::assertSame(['The index rule and set() plus a link'], $this->labels($context));
    }

    #[Test]
    public function replacesThePreviousDocumentsSectionsRatherThanAppending(): void
    {
        $context     = $this->convert("## From the first document\n");
        $environment = $this->environment($context);

        new MarkdownConverter($environment)->convert("## From the second\n");

        // One document at a time reaches the context, so a second conversion through the
        // same context must not accumulate the first document's sections into the second's.
        static::assertSame(['from-the-second'], $this->fragments($context));
    }

    #[Test]
    public function skipsAHeadingThatCarriesNoId(): void
    {
        // The permalink extension is what assigns ids. A deployment without it still builds
        // a valid site, so a heading with no id is skipped rather than refused.
        $context = new PageContext();
        $env     = new Environment();
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addEventListener(DocumentParsedEvent::class, new HeadingListener($context), HeadingListener::PRIORITY);

        new MarkdownConverter($env)->convert("## No id here\n");

        static::assertSame([], $context->sections());
    }

    #[Test]
    public function skipsAHeadingWhoseIdIsEmpty(): void
    {
        // An empty id is an anchor nothing can address, so it is not a link target.
        $context = $this->convertWithId('');

        static::assertSame([], $context->sections());
    }

    #[Test]
    public function skipsAHeadingWhoseIdIsNotAString(): void
    {
        // Nothing in the renderer produces this, but the data bag takes any value and the
        // id is what becomes a fragment. A non-string is skipped as unlinkable rather than
        // reaching the value object, which would refuse it.
        $context = $this->convertWithId(7);

        static::assertSame([], $context->sections());
    }

    #[Test]
    public function trimsWhitespaceThatEndsUpAtTheEdgeOfTheLabel(): void
    {
        // The block parser strips the space after the marker, but an entity is decoded
        // afterwards, so the space it yields survives into the heading's text.
        $context = $this->convert("## &#32;Entity space\n");

        static::assertSame(['entity-space'], $this->fragments($context));
        static::assertSame(['Entity space'], $this->labels($context));
    }

    private function convert(string $markdown): PageContext
    {
        $context = new PageContext();

        new MarkdownConverter($this->environment($context))->convert($markdown);

        return $context;
    }

    private function convertWithId(mixed $id): PageContext
    {
        $context = new PageContext();

        $heading = new Heading(2);
        $heading->data->set(
            key  : 'attributes/id',
            value: $id,
        );
        $heading->appendChild(new Text('Install it'));

        $document = new Document();
        $document->appendChild($heading);

        new HeadingListener($context)(new DocumentParsedEvent($document));

        return $context;
    }

    private function environment(PageContext $context): Environment
    {
        $environment = new Environment([
            'heading_permalink' => [
                'id_prefix'           => '',
                'apply_id_to_heading' => true,
                'fragment_prefix'     => '',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addEventListener(
            DocumentParsedEvent::class,
            new HeadingListener($context),
            HeadingListener::PRIORITY,
        );

        return $environment;
    }

    /**
     * @return list<non-empty-string>
     */
    private function fragments(PageContext $context): array
    {
        $fragments = [];

        foreach ($context->sections() as $heading) {
            $fragments[] = $heading->fragment;
        }

        return $fragments;
    }

    /**
     * @return list<string>
     */
    private function labels(PageContext $context): array
    {
        $labels = [];

        foreach ($context->sections() as $heading) {
            $labels[] = $heading->label;
        }

        return $labels;
    }

    /**
     * @return list<int>
     */
    private function levels(PageContext $context): array
    {
        $levels = [];

        foreach ($context->sections() as $heading) {
            $levels[] = $heading->level;
        }

        return $levels;
    }
}
