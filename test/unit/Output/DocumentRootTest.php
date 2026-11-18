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
use CommonDoc\Output\Style;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentRoot::class)]
#[CoversMethod(DocumentRoot::class, '__construct')]
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
    public function keepsTheFileRelativeWhileTheAddressCarriesThePrefix(): void
    {
        $root = new DocumentRoot('/repo/');

        static::assertStringStartsWith('/repo/', $root->published('a/b.md'));
        static::assertStringStartsNotWith('/', $root->file('a/b.md'));
    }

    #[Test]
    public function publishesADocumentAsADirectoryAddress(): void
    {
        $root = new DocumentRoot('/');

        static::assertSame('/guide/setup/', $root->published('guide/setup.md'));
        static::assertSame('guide/setup/index.html', $root->file('guide/setup.md'));
    }

    #[Test]
    public function publishesPlainAddressesWithTheSuffixInPlace(): void
    {
        $root = new DocumentRoot('/', Style::Plain);

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
}
