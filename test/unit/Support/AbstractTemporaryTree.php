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

namespace CommonDocTest\Support;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * A test case with a throwaway directory tree.
 *
 * The scans are the only code in this package that touches a filesystem, so they
 * are the only tests that need a real one. A base class rather than a trait
 * because the house naming rule requires a trait name to end in `Trait`, and
 * `TemporaryTreeTrait` says nothing the class name does not already say.
 */
abstract class AbstractTemporaryTree extends TestCase
{
    protected string $tree = '';

    protected function makeTree(): string
    {
        $this->tree =
            sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'commondoc-input-'
            . bin2hex(random_bytes(8));

        mkdir($this->tree);

        return $this->tree;
    }

    protected function removeTree(): void
    {
        if (! is_dir($this->tree)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tree, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());

                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($this->tree);
    }

    /**
     * Create one file, creating any directory it needs on the way.
     */
    protected function writeFile(string $relativePath, string $contents = "body\n"): void
    {
        $path = $this->tree . DIRECTORY_SEPARATOR . $relativePath;
        $dir  = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        file_put_contents($path, $contents);
    }
}
