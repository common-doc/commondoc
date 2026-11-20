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
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function chmod;
use function exec;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_writable;
use function rmdir;
use function sprintf;
use function unlink;
use function vsprintf;

/**
 * A throwaway checkout whose origin is a GitHub URL.
 *
 * Reading repository identity means asking git real questions, so a test that stubs the
 * shell tests the stub. This is the smallest checkout that answers all of them, built the
 * same way in every test that needs one.
 */
final class GitCheckout
{
    /**
     * Initialize a repository with one commit and a GitHub origin.
     */
    public static function create(string $directory): void
    {
        file_put_contents(
            filename: "{$directory}/README.md",
            data    : "# Fixture\n",
        );

        self::run($directory, arguments: 'init --quiet');
        self::run($directory, arguments: 'remote add origin https://github.com/example/fixture.git');
        self::run($directory, arguments: 'config user.email fixture@example.com');
        self::run($directory, arguments: 'config user.name Fixture');
        self::run($directory, arguments: 'add README.md');
        self::run($directory, arguments: 'commit --quiet -m fixture');
    }

    /**
     * Remove a directory tree, clearing the read-only bit git sets on its objects.
     *
     * Windows refuses to delete a file carrying that bit, so a plain recursive delete
     * leaves the fixture behind and the next run reads a repository the previous one
     * made.
     */
    public static function remove(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $path = $entry->getPathname();

            if (! is_writable($path)) {
                chmod(
                    filename   : $path,
                    permissions: 0o777,
                );
            }

            if ($entry->isDir()) {
                rmdir($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }

    /**
     * One git command, which must succeed.
     *
     * @param string ...$values Further sprintf values for the command
     */
    public static function run(string $directory, string $arguments, string ...$values): void
    {
        $output = [];
        $code   = 0;

        exec(
            command    : sprintf('git -C "%s" %s 2>&1', $directory, vsprintf($arguments, $values)),
            output     : $output,
            result_code: $code,
        );

        Assert::assertSame(0, $code, sprintf('git %s failed: %s', $arguments, implode("\n", $output)));
    }
}
