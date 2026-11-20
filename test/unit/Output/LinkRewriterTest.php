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

use CommonDoc\Input\Source;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\LinkRewriter;
use CommonDoc\Output\PathType;
use CommonDoc\Output\SourceLinks;
use CommonDoc\Output\SourcePath;
use CommonDoc\View\Page;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\AbstractWebResource;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The rewriter answers one question per link: what path does this target have
 * on the published site. Each test therefore builds a document that holds the
 * page identifying it, runs the listener, and asserts on the node that came out.
 *
 * The two failure tests matter as much as the successes. A link that resolves to
 * nothing is a documented build error, so a rewriter that quietly published the
 * target unchanged would pass every happy-path test and still ship a dead site.
 */
#[CoversClass(LinkRewriter::class)]
#[CoversClass(SourceLinks::class)]
#[CoversClass(SourcePath::class)]
#[CoversMethod(LinkRewriter::class, '__construct')]
#[CoversMethod(LinkRewriter::class, '__invoke')]
final class LinkRewriterTest extends TestCase
{
    private const array DOCUMENTS = [
        'guide/errors.md',
        'guide/intro.md',
        'guide/setup.md',
        'index.md',
    ];

    private const array ASSETS = [
        'images/d.png',
        'site.css',
    ];

    /**
     * @param list<AbstractWebResource> $nodes
     */
    private static function document(?string $sourcePath, array $nodes): Document
    {
        $document  = new Document();
        $paragraph = new Paragraph();

        $document->appendChild($paragraph);

        foreach ($nodes as $node) {
            $paragraph->appendChild($node);
        }

        if (null !== $sourcePath) {
            $document->data->set('commondoc.page', new Page(
                label       : 'Setup',
                projectName : 'CommonDoc',
                projectSlug : 'commondoc',
                sourcePath  : $sourcePath,
                url         : '/guide/setup/',
                versionLabel: '1.0',
            ));
        }

        return $document;
    }

    #[Test]
    public function failsWhenATargetClimbsAboveTheSourceRoot(): void
    {
        $link = new Link('../outside.md');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The path "./../outside.md" leaves the source root.');

        $this->dispatch([$link], sourcePath: 'index.md');
    }

    #[Test]
    public function failsWhenTheTargetIsNotInTheScan(): void
    {
        $link = new Link('missing.md');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The document "guide/setup.md" links to "missing.md", which resolves to "guide/missing.md" and is neither a document nor an asset the scan found.',
        );

        $this->dispatch([$link], sourcePath: 'guide/setup.md');
    }

    #[Test]
    public function keepsTheFragmentWhileMappingThePath(): void
    {
        $link = new Link('intro.md#install');

        static::assertSame('/repo/guide/intro/#install', $this->targetOf($link, from: 'guide/setup.md'));
    }

    #[Test]
    public function keepsTheQueryWhileMappingThePath(): void
    {
        $link = new Link('intro.md?tab=2');

        static::assertSame('/repo/guide/intro/?tab=2', $this->targetOf($link, from: 'guide/setup.md'));
    }

    #[Test]
    public function keepsTheSuffixWhenATargetResolvesToTheRepository(): void
    {
        // The target climbs out of the source root but stays inside the repository,
        // so the URL has to be composed from the repository path rather than from
        // the site path it resolved to.
        $link = new Link('../../contributing.md#the-rule');

        static::assertSame(
            'https://github.com/example/message-bus/blob/abc123/docs/contributing.md#the-rule',
            $this->targetOf(
                $link,
                from: 'message-bus/v2/guide/setup.md',
                origins: ['/message-bus/v2/' => new Source(
                    'https://github.com/example/message-bus',
                    'abc123',
                    'docs/2.0',
                )],
            ),
        );
    }

    #[Test]
    public function keepsTheSuffixWhileMappingAnAsset(): void
    {
        // An asset goes through the second translation, and that branch has to
        // carry the suffix through too. Without this case the difference between
        // publishing the path and publishing the path with its fragment is
        // invisible, and both halves of the concatenation survive mutation.
        $image = new Image('../images/d.png#zoom');

        static::assertSame('/repo/images/d.png#zoom', $this->targetOf($image, from: 'guide/setup.md'));
    }

    #[Test]
    public function leavesADocumentWithNoPageUntouched(): void
    {
        // A preview render has no page, so there is no document to resolve a
        // relative target against. It is left as written rather than refused.
        $link = new Link('nowhere.md');

        $this->dispatch([$link], sourcePath: null);

        static::assertSame('nowhere.md', $link->getUrl());
    }

    #[Test]
    public function leavesAFragmentOnlyTargetUntouched(): void
    {
        $link = new Link('#install');

        static::assertSame('#install', $this->targetOf($link, from: 'guide/setup.md'));
    }

    #[Test]
    public function leavesExternalTargetsUntouched(): void
    {
        $absolute = new Link('https://example.com/guide/setup.md');
        $mail     = new Link('mailto:docs@example.com');
        $protocol = new Link('//cdn.example.com/site.css');
        $image    = new Image('https://example.com/d.png');

        $this->dispatch([$absolute, $mail, $protocol, $image], sourcePath: 'guide/setup.md');

        static::assertSame('https://example.com/guide/setup.md', $absolute->getUrl());
        static::assertSame('mailto:docs@example.com', $mail->getUrl());
        static::assertSame('//cdn.example.com/site.css', $protocol->getUrl());
        static::assertSame('https://example.com/d.png', $image->getUrl());
    }

    #[Test]
    public function publishesAnImageAtItsOwnPath(): void
    {
        $image = new Image('../images/d.png');

        static::assertSame('/repo/images/d.png', $this->targetOf($image, from: 'guide/setup.md'));
    }

    #[Test]
    public function refusesAnAbsoluteTargetThatIsNotInTheScanEvenWhenTheRootHasARepository(): void
    {
        // A leading slash is relative to the site root, so it says nothing about the
        // repository. Falling back to the source for it would answer a question the
        // document never asked.
        $link = new Link('/missing.md');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is neither a document nor an asset the scan found');

        $this->dispatch(
            [$link],
            sourcePath: 'message-bus/v2/guide/setup.md',
            origins: ['/message-bus/v2/' => new Source(
                'https://github.com/example/message-bus',
                'abc123',
                'docs/2.0',
            )],
        );
    }

    #[Test]
    public function refusesATargetThatClimbsAboveTheRepositoryItself(): void
    {
        // A repository is the outermost space there is. A target that leaves it names
        // no file anywhere, so there is nothing to link to and the refusal stands.
        $link = new Link('../../../../outside.md');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('leaves the source root');

        $this->dispatch(
            [$link],
            sourcePath: 'message-bus/v2/benchmark-results.md',
            origins: ['/message-bus/v2/' => new Source(
                'https://github.com/example/message-bus',
                'abc123',
                'docs/2.0',
            )],
        );
    }

    #[Test]
    public function refusesATargetThatStaysInsideTheMountButIsNotInTheScan(): void
    {
        // The repository branch must not swallow a genuine broken link: a target that
        // never left the mount is still a dead link on the published site.
        $link = new Link('missing.md');

        $this->expectException(RuntimeException::class);

        $this->dispatch(
            [$link],
            sourcePath: 'message-bus/v2/guide/setup.md',
            origins: ['/message-bus/v2/' => new Source('https://github.com/example/message-bus', 'abc123')],
        );
    }

    #[Test]
    public function resolvesACurrentDirectorySegment(): void
    {
        $link = new Link('./intro.md');

        static::assertSame('/repo/guide/intro/', $this->targetOf($link, from: 'guide/setup.md'));
    }

    #[Test]
    public function resolvesAnAbsoluteTargetAgainstTheRoot(): void
    {
        // A leading slash is the reader's only absolute space, and the published
        // site begins at the root, so the target is not resolved against the
        // directory holding the document.
        $link = new Link('/index.md');

        static::assertSame('/repo/', $this->targetOf($link, from: 'guide/setup.md'));
    }

    #[Test]
    public function resolvesAParentTargetAgainstTheDocumentDirectory(): void
    {
        $link = new Link('../errors.md');

        static::assertSame('/repo/guide/errors/', $this->targetOf($link, from: 'guide/deep/setup.md'));
    }

    #[Test]
    public function rewritesASiblingDocumentLink(): void
    {
        $link = new Link('intro.md');

        static::assertSame('/repo/guide/intro/', $this->targetOf($link, from: 'guide/setup.md'));
    }

    #[Test]
    public function rewritesATargetAboveTheMountToTheRepositoryItCameFrom(): void
    {
        // A document may point at a directory the site does not publish: a benchmark
        // note names `benchmarks/`, a contributing guide names a workflow file. Those
        // are links to the source, and refusing them would make a repository's own
        // documentation unpublishable without editing it.
        $link = new Link('../../benchmarks/');

        static::assertSame(
            'https://github.com/example/message-bus/blob/abc123/benchmarks',
            $this->targetOf(
                $link,
                from: 'message-bus/v2/benchmark-results.md',
                origins: ['/message-bus/v2/' => new Source(
                    'https://github.com/example/message-bus',
                    'abc123',
                    'docs/2.0',
                )],
            ),
        );
    }

    #[Test]
    public function rewritesATargetThatClimbsAboveARootMountedAtTheSiteRoot(): void
    {
        // A site that mounts a repository's docs directory at `/` is only as deep as
        // that directory, so a link written against the repository leaves the site on
        // its second parent segment. The repository is still the space it names, and
        // the climb is why the site-space answer cannot be the only one consulted.
        $link = new Link('../../benchmarks/');

        static::assertSame(
            'https://github.com/example/message-bus/blob/abc123/benchmarks',
            $this->targetOf(
                $link,
                from: 'benchmark-results.md',
                origins: ['/' => new Source(
                    'https://github.com/example/message-bus',
                    'abc123',
                    'docs/2.0',
                )],
            ),
        );
    }

    #[Test]
    public function stillRefusesATargetAboveTheMountWhenTheRootHasNoRepository(): void
    {
        // A `path` root assembled outside a repository has no source to point at, so
        // the refusal is the honest answer rather than a link to nowhere.
        $link = new Link('../../benchmarks/');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is neither a document nor an asset the scan found');

        $this->dispatch([$link], sourcePath: 'message-bus/v2/benchmark-results.md');
    }

    #[Test]
    public function usesTheFileLayoutWhenTheRootPublishesFiles(): void
    {
        $link = new Link('intro.md');

        static::assertSame(
            '/repo/guide/intro.html',
            $this->targetOf($link, from: 'guide/setup.md', pathType: PathType::File),
        );
    }

    /**
     * @param list<AbstractWebResource> $nodes
     *
     * @param array<non-empty-string, Source> $origins
     */
    private function dispatch(
        array $nodes,
        ?string $sourcePath,
        PathType $pathType = PathType::Directory,
        array $origins = [],
    ): void {
        $rewriter = new LinkRewriter(
            new DocumentRoot('/repo/', $pathType),
            self::DOCUMENTS,
            self::ASSETS,
            $origins,
        );

        $rewriter(new DocumentParsedEvent(self::document($sourcePath, $nodes)));
    }

    /**
     * Runs the listener over one document and returns the rewritten target of
     * the node under test.
     *
     * @param array<non-empty-string, Source> $origins
     */
    private function targetOf(
        AbstractWebResource $node,
        string $from,
        PathType $pathType = PathType::Directory,
        array $origins = [],
    ): string {
        $this->dispatch([$node], $from, $pathType, $origins);

        return $node->getUrl();
    }
}
