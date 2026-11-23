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

use CommonDoc\Output\DocumentPath;
use CommonDoc\Output\PathType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The directory index rule, on its own.
 *
 * The rule is stated once here and both answers come from it, so it is pinned directly
 * rather than through the two classes that delegate to it.
 */
#[CoversClass(DocumentPath::class)]
#[CoversMethod(DocumentPath::class, '__construct')]
#[CoversMethod(DocumentPath::class, 'file')]
#[CoversMethod(DocumentPath::class, 'isIndex')]
#[CoversMethod(DocumentPath::class, 'pathOf')]
final class DocumentPathTest extends TestCase
{
    #[Test]
    public function answersTheRootIndexAsTheOutputRootsOwnFile(): void
    {
        $path = new DocumentPath();

        static::assertSame('index.html', $path->file('README'));
        static::assertSame('index.html', $path->file('index'));
        static::assertSame('', $path->pathOf('README'));
    }

    #[Test]
    public function neverTreatsADocumentNamedAfterAnIndexDirectoryAsAnIndex(): void
    {
        $path = new DocumentPath();

        static::assertFalse($path->isIndex('guide/readme/setup'));
        static::assertSame('guide/readme/setup/', $path->pathOf('guide/readme/setup'));
    }

    #[Test]
    public function servesAContentDocumentAtItsOwnPath(): void
    {
        $path = new DocumentPath();

        static::assertFalse($path->isIndex('guide/setup'));
        static::assertSame('guide/setup/', $path->pathOf('guide/setup'));
        static::assertSame('guide/setup/index.html', $path->file('guide/setup'));
    }

    #[Test]
    public function servesADirectoryAtItselfWhateverTheIndexIsNamed(): void
    {
        $path = new DocumentPath();

        static::assertSame('guide/', $path->pathOf('guide/README'));
        static::assertSame('guide/', $path->pathOf('guide/index'));
        static::assertSame('guide/index.html', $path->file('guide/README'));
        static::assertSame('guide/index.html', $path->file('guide/index'));
    }

    #[Test]
    public function servesTheAddressItselfWhenTheSiteUsesFilePaths(): void
    {
        $path = new DocumentPath(PathType::File);

        static::assertSame('guide/setup.html', $path->pathOf('guide/setup'));
        static::assertSame('guide/setup.html', $path->file('guide/setup'));
    }

    #[Test]
    public function stillServesADirectoryByItsIndexFileWhenTheSiteUsesFilePaths(): void
    {
        $path = new DocumentPath(PathType::File);

        // The address is the file in this mode, so an index is written under the name a
        // server resolves for the directory instead of under its own name.
        static::assertSame('guide/index.html', $path->pathOf('guide/README'));
        static::assertSame('guide/index.html', $path->file('guide/README'));
        static::assertSame('index.html', $path->pathOf('README'));
        static::assertSame('index.html', $path->file('README'));
    }

    #[Test]
    public function treatsTheIndexNamesWithoutRegardToCase(): void
    {
        $path = new DocumentPath();

        static::assertTrue($path->isIndex('guide/ReAdMe'));
        static::assertSame('guide/index.html', $path->file('guide/ReAdMe'));
    }
}
