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
use CommonDoc\Output\PathType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentRoot::class)]
#[CoversMethod(DocumentRoot::class, '__construct')]
#[CoversMethod(DocumentRoot::class, 'asset')]
#[CoversMethod(DocumentRoot::class, 'file')]
#[CoversMethod(DocumentRoot::class, 'published')]
final class DocumentRootTest extends TestCase
{
    #[Test]
    public function acceptsAPrefixThatAlreadyCarriesItsSlash(): void
    {
        $root = new DocumentRoot('/repo/');

        static::assertSame('/repo/guide/', $root->published('guide/index.md'));
    }

    #[Test]
    public function keepsTheFileRelativeWhileThePublishedPathCarriesThePrefix(): void
    {
        $root = new DocumentRoot('/repo/');

        static::assertStringStartsWith('/repo/', $root->published('a/b.md'));
        static::assertStringStartsNotWith('/', $root->file('a/b.md'));
    }

    #[Test]
    public function publishesADocumentAsADirectoryPath(): void
    {
        $root = new DocumentRoot('/');

        static::assertSame('/guide/setup/', $root->published('guide/setup.md'));
        static::assertSame('guide/setup/index.html', $root->file('guide/setup.md'));
    }

    #[Test]
    public function publishesADocumentWhoseSuffixIsUppercase(): void
    {
        $root = new DocumentRoot('/');

        // Markdown::scan matches its suffix without regard to case, so a document
        // it reports must be translatable. Refusing upper case here would fail a
        // build on a document the scan had already accepted.
        static::assertSame('/guide/setup/', $root->published('guide/setup.MD'));
        static::assertSame('guide/setup/index.html', $root->file('guide/setup.MD'));
    }

    #[Test]
    public function publishesAnAssetIdenticallyInEitherPathType(): void
    {
        static::assertSame(
            new DocumentRoot('/', PathType::File)->asset('a/b.css'),
            new DocumentRoot('/', PathType::Directory)->asset('a/b.css'),
        );
    }

    #[Test]
    public function publishesAnAssetWhereItSits(): void
    {
        $root = new DocumentRoot('/repo/');

        // An asset has no suffix to map and no index to imply, so the path is
        // the base path and the path. Anything else would publish an image at an
        // path the copy pass does not write it to.
        static::assertSame('/repo/images/diagram.png', $root->asset('images/diagram.png'));
        static::assertSame('/repo/diagram.png', $root->asset('diagram.png'));
    }

    #[Test]
    public function publishesANestedIndexAsAFilePath(): void
    {
        $root = new DocumentRoot('/', PathType::File);

        static::assertSame('/guide/index.html', $root->published('guide/index.md'));
        static::assertSame('guide/index.html', $root->file('guide/index.md'));
    }

    #[Test]
    public function publishesDocumentsAsFilePathsWithTheSuffixInPlace(): void
    {
        $root = new DocumentRoot('/', PathType::File);

        static::assertSame('/guide/setup.html', $root->published('guide/setup.md'));
        static::assertSame('guide/setup.html', $root->file('guide/setup.md'));
        static::assertSame('/index.html', $root->published('index.md'));
        static::assertSame('index.html', $root->file('index.md'));
    }

    #[Test]
    public function publishesTheRootDocumentAtTheBasePath(): void
    {
        $root = new DocumentRoot('/');

        static::assertSame('/', $root->published('index.md'));
        static::assertSame('index.html', $root->file('index.md'));
    }

    #[Test]
    public function publishesUnderAConfiguredPrefix(): void
    {
        $root = new DocumentRoot('/repo');

        static::assertSame('/repo/', $root->published('index.md'));
        static::assertSame('/repo/guide/setup/', $root->published('guide/setup.md'));
        static::assertSame('guide/setup/index.html', $root->file('guide/setup.md'));
    }

    #[Test]
    public function refusesABasePathWithoutALeadingSlash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with a slash');

        new DocumentRoot('repo');
    }

    #[Test]
    public function refusesADoubledTrailingSlash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('more than one trailing slash');

        new DocumentRoot('/repo//');
    }

    #[Test]
    public function refusesAnAbsolutePath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DocumentRoot('/')->published('/guide/setup.md');
    }

    #[Test]
    public function refusesAnEmptyBasePath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DocumentRoot('');
    }

    #[Test]
    public function refusesAPathThatIsNotMarkdown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a relative markdown path');

        new DocumentRoot('/')->file('guide/setup.html');
    }

    #[Test]
    public function refusesAPathThatLeavesTheRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DocumentRoot('/')->file('../setup.md');
    }

    #[Test]
    public function treatsAnIndexDocumentAsItsDirectoryIndex(): void
    {
        $root = new DocumentRoot('/');

        static::assertSame('/guide/', $root->published('guide/index.md'));
        static::assertSame('guide/index.html', $root->file('guide/index.md'));
    }

    #[Test]
    public function treatsOnlyAnExactIndexSegmentAsADirectoryIndex(): void
    {
        $root = new DocumentRoot('/');

        static::assertSame('/myindex/', $root->published('myindex.md'));
        static::assertSame('myindex/index.html', $root->file('myindex.md'));
        static::assertSame('/a/indexer/', $root->published('a/indexer.md'));
    }
}
