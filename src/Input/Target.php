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

namespace CommonDoc\Input;

use CommonDoc\Exception\InvalidArgumentException;
use FilesystemIterator;
use Psl\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function explode;
use function is_dir;
use function is_readable;
use function rtrim;
use function sprintf;
use function str_replace;
use function trim;

/**
 * Where a repository keeps its documentation: the target.
 *
 * This package documents itself from `docs/v1`, which is this repository's habit and
 * not a rule. A repository migrating from DocBook keeps its sources in `docs/book`,
 * and one that publishes per-major-version keeps a directory per major. None of that
 * is this package's decision, so the target is configuration with a default.
 *
 * The target is resolved against a repository root, so one value applies to every
 * repository a site is composed from: a site of a dozen repositories states the target
 * once, and the repository that differs states its own.
 *
 * A repository whose documentation is not at its target is refused rather than
 * skipped. A target that matches nothing builds cleanly and publishes nothing, which
 * an operator cannot tell from a repository that legitimately has no pages.
 *
 * @api
 */
final readonly class Target
{
    /**
     * The target this package documents itself from, inherited by every repository in a
     * composed site unless its own entry says otherwise.
     */
    public const string DEFAULT = 'docs/v1';

    /**
     * The target, relative to a repository root, with forward slashes.
     */
    public string $location;

    /**
     * @param string $location The directory holding a repository's documentation, relative to
     *                         that repository's root
     *
     * @throws InvalidArgumentException When the target is empty, or can escape the repository
     *                                  it is resolved against
     */
    public function __construct(string $location = self::DEFAULT)
    {
        $this->location = self::normalize($location);
    }

    /**
     * Whether any file beneath the directory is a Markdown document.
     *
     * The rule comes from `Markdown` rather than being restated, so a target cannot be
     * consumable here and empty there. Only the first document is looked for, because
     * the question is whether the target holds documentation at all.
     */
    private static function holdsDocumentation(string $directory): bool
    {
        $files = new RecursiveIteratorIterator(
            iterator: new RecursiveDirectoryIterator(
                directory: $directory,
                flags    : FilesystemIterator::SKIP_DOTS,
            ),
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && Str\ends_with_ci($file->getFilename(), suffix: Markdown::EXTENSION)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A target that names one directory inside a repository, never outside it.
     *
     * Backslashes become forward slashes, because an operator writes whichever their
     * shell taught them. The traversal check is per segment rather than a search of the
     * whole string: a directory may legitimately be named `v1..2`, and a substring test
     * would refuse it while a segment test cannot.
     *
     * @throws InvalidArgumentException When the value cannot name a directory relative to a
     *                                  repository root
     */
    private static function normalize(string $location): string
    {
        $normalized = trim(
            string    : trim(
                string: str_replace(
                    search : '\\',
                    replace: '/',
                    subject: $location,
                ),
            ),
            characters: '/',
        );

        if ('' === $normalized) {
            throw new InvalidArgumentException(
                'The documentation target is empty, so there is no directory to look for. Name one relative to the repository root, or omit the value to accept the default.',
            );
        }

        foreach (explode('/', $normalized) as $segment) {
            if ('..' === $segment) {
                throw new InvalidArgumentException(sprintf(
                    'The documentation target "%s" can leave the repository it is resolved against, so it is refused.',
                    $normalized,
                ));
            }
        }

        return $normalized;
    }

    /**
     * The directory to walk, once it is known to hold documentation.
     *
     * Both failures are refused as one, because to an operator they are one failure: the
     * documentation is not at the target. Which of the two it was is in the message.
     *
     * @throws InvalidArgumentException When the directory is missing, unreadable, or holds no
     *                                  Markdown document
     */
    public function assertConsumable(string $repositoryRoot): string
    {
        $directory = $this->at($repositoryRoot);

        if (! is_dir($directory) || ! is_readable($directory)) {
            throw new InvalidArgumentException(sprintf(
                'The documentation target "%s" resolves to "%s", which is not a readable directory. A composed site names each repository\'s target, and the default is "%s".',
                $this->location,
                $directory,
                self::DEFAULT,
            ));
        }

        if (self::holdsDocumentation($directory)) {
            return $directory;
        }

        throw new InvalidArgumentException(sprintf(
            'The documentation target "%s" resolves to "%s", which holds no "%s" document. A repository with nothing to publish belongs out of the composition rather than in it as an empty root.',
            $this->location,
            $directory,
            Markdown::EXTENSION,
        ));
    }

    /**
     * The directory to walk, given a repository's root.
     *
     * A root of `.` answers with the target itself, so a single-repository build and a
     * composed one resolve through the same code. The join is textual and touches no
     * filesystem, which keeps this type testable without a tree.
     */
    public function at(string $repositoryRoot): string
    {
        $root = rtrim(
            string    : str_replace(
                search : '\\',
                replace: '/',
                subject: $repositoryRoot,
            ),
            characters: '/',
        );

        if ('' === $root || '.' === $root) {
            return $this->location;
        }

        return "{$root}/{$this->location}";
    }
}
