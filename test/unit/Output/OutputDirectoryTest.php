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

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Output\OutputDirectory;
use CommonDocTest\Support\AbstractTemporaryTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;

use function is_dir;
use function is_file;

/**
 * The directory a build writes into, cleared before the first file is written.
 *
 * The guards matter as much as the clearing: this deletes files, so a path that could not be
 * a site's output has to be refused before anything is removed.
 */
#[CoversClass(OutputDirectory::class)]
#[CoversMethod(OutputDirectory::class, '__construct')]
#[CoversMethod(OutputDirectory::class, 'clear')]
final class OutputDirectoryTest extends AbstractTemporaryTree
{
    #[Test]
    public function allowsAnOutputBesideItsSources(): void
    {
        $this->makeTree();

        static::assertInstanceOf(
            OutputDirectory::class,
            new OutputDirectory("{$this->tree}/build", ["{$this->tree}/docs"]),
        );
    }

    #[Test]
    public function clearsEverythingInsideAndKeepsTheDirectory(): void
    {
        $this->makeTree();
        $this->writeFile('build/stale/index.html');
        $this->writeFile('build/old.css');

        new OutputDirectory("{$this->tree}/build")->clear();

        static::assertTrue(is_dir("{$this->tree}/build"));
        static::assertDirectoryDoesNotExist("{$this->tree}/build/stale");
        static::assertFalse(is_file("{$this->tree}/build/old.css"));
    }

    #[Test]
    public function leavesAMissingDirectoryAlone(): void
    {
        $this->makeTree();

        new OutputDirectory("{$this->tree}/build")->clear();

        static::assertDirectoryDoesNotExist("{$this->tree}/build");
    }

    #[Test]
    public function refusesAFilesystemRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OutputDirectory('/');
    }

    #[Test]
    public function refusesAnEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OutputDirectory('');
    }

    #[Test]
    public function refusesAnOutputThatHoldsOneOfItsSources(): void
    {
        $this->makeTree();

        $this->expectException(InvalidArgumentException::class);

        // Clearing this would delete the documentation the build is reading.
        new OutputDirectory("{$this->tree}", ["{$this->tree}/docs"]);
    }

    #[Test]
    public function refusesTheWorkingDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OutputDirectory('.');
    }
}
