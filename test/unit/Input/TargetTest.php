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

namespace CommonDocTest\Input;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Target;
use DirectoryIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function str_replace;
use function sys_get_temp_dir;
use function tempnam;
use function touch;
use function unlink;

#[CoversClass(Target::class)]
#[CoversMethod(Target::class, 'at')]
#[CoversMethod(Target::class, 'assertConsumable')]
final class TargetTest extends TestCase
{
    /**
     * Removes a tree the test built, so a failing assertion cannot leave it behind.
     */
    private static function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }

            return;
        }

        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            self::removeTree($entry->getPathname());
        }

        rmdir($path);
    }

    #[Test]
    public function acceptsAPathWrittenWithBackslashes(): void
    {
        static::assertSame('docs/v1', new Target('docs\\v1')->location);
    }

    #[Test]
    public function acceptsTheLocationTheOperatorSupplies(): void
    {
        // The whole point of the type: DocBook sources, a per-major directory, anything.
        static::assertSame(
            'checkouts/address/docs/book',
            new Target('docs/book')->at(repositoryRoot: 'checkouts/address'),
        );
    }

    #[Test]
    public function allowsADirectoryNameThatMerelyContainsDots(): void
    {
        // The traversal check is per segment, so a legitimate name is not caught by it.
        static::assertSame('docs/v1..2', new Target('docs/v1..2')->location);
    }

    #[Test]
    public function answersWithTheDirectoryOnceItHoldsADocumentInASubdirectory(): void
    {
        $root = $this->temporaryDirectory();
        mkdir(
            directory: "{$root}/docs/v1/concepts",
            recursive: true,
        );
        touch(filename: "{$root}/docs/v1/concepts/index.md");

        try {
            // Recursive, so a root that groups its pages is accepted.
            static::assertSame(
                str_replace(
                    search : '\\',
                    replace: '/',
                    subject: $root,
                )
                    . '/docs/v1',
                new Target()->assertConsumable($root),
            );
        } finally {
            self::removeTree($root);
        }
    }

    #[Test]
    public function answersWithTheTargetItselfForTheRepositoryRoot(): void
    {
        // `.` is the default site's root, so a single-repository build and a composed one
        // resolve through the same code rather than through a special case.
        static::assertSame('docs/v1', new Target()->at(repositoryRoot: '.'));
        static::assertSame('docs/v1', new Target()->at(repositoryRoot: ''));
        static::assertSame('docs/v1', new Target()->at(repositoryRoot: './'));
    }

    #[Test]
    public function refusesAnEmptyTarget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('documentation target is empty');

        new Target('   ');
    }

    /**
     * A directory that exists but cannot be read is refused too.
     *
     * Linux only because permissions do not stop the owner on Windows, where a test
     * asserting the refusal would pass for the wrong reason. It is what separates "is a
     * directory" from "is readable": with the check reduced to one of the two, a target
     * under a directory the process cannot enter would be walked and fail obscurely
     * instead of being refused with the target named.
     */
    #[Test]
    #[RequiresOperatingSystemFamily('Linux')]
    public function refusesAnUnreadableTarget(): void
    {
        $root = $this->temporaryDirectory();
        mkdir(
            directory: "{$root}/docs/v1",
            recursive: true,
        );
        touch(filename: "{$root}/docs/v1/index.md");
        chmod(
            filename   : "{$root}/docs/v1",
            permissions: 0o000,
        );

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('is not a readable directory');

            new Target()->assertConsumable($root);
        } finally {
            chmod(
                filename   : "{$root}/docs/v1",
                permissions: 0o755,
            );
            self::removeTree($root);
        }
    }

    #[Test]
    public function refusesATargetThatCanLeaveTheRepository(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('can leave the repository');

        new Target('docs/../../etc');
    }

    #[Test]
    public function refusesATargetThatHoldsNoMarkdown(): void
    {
        $root = $this->temporaryDirectory();
        mkdir(
            directory: "{$root}/docs/v1",
            recursive: true,
        );
        touch(filename: "{$root}/docs/v1/notes.txt");

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('holds no ".md" document');

            new Target()->assertConsumable($root);
        } finally {
            self::removeTree($root);
        }
    }

    #[Test]
    public function refusesATargetWithNoDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a readable directory');

        new Target()->assertConsumable($this->missingDirectory());
    }

    #[Test]
    public function resolvesAgainstARepositoryRoot(): void
    {
        static::assertSame(
            'checkouts/address/docs/v1',
            new Target()->at(repositoryRoot: 'checkouts/address'),
        );
    }

    #[Test]
    public function trimsSurroundingSlashes(): void
    {
        static::assertSame('docs/v1', new Target('/docs/v1/')->location);
    }

    private function missingDirectory(): string
    {
        $root = $this->temporaryDirectory();
        rmdir($root);

        return $root;
    }

    private function temporaryDirectory(): string
    {
        $path = (string) tempnam(
            directory: sys_get_temp_dir(),
            prefix   : 'commondoc-target-',
        );
        unlink($path);
        mkdir(directory: $path);

        return $path;
    }
}
