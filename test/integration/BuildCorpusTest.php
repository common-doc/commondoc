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

namespace CommonDocTestIntegration;

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\CommandHandler\BuildSiteHandler;
use CommonDoc\Input\Asset;
use CommonDoc\Input\Markdown;
use CommonDoc\Input\Tree;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\Output\LinkRewriter;
use CommonDoc\Record\BuildRecord;
use FilesystemIterator;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Webware\MessageBus\MessageBusInterface;
use Webware\MessageBus\MessageStatus;
use Webware\MessageBus\ResultInterface;

use function array_keys;
use function bin2hex;
use function copy;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sort;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const SORT_STRING;

/**
 * Builds the repository's own documentation and asserts the result.
 *
 * Every other test in this suite either drives one collaborator or dispatches a
 * hand-made tree through the bus. This one builds `docs/v1`, which is shaped to
 * reach every pass: a nested index document and a nested leaf, a relative link
 * across directories, an absolute link from the root, a suffix-bearing link, and
 * two assets of different kinds referenced from documents.
 *
 * It exists so a change to any phase is measured against the whole output rather
 * than against the one collaborator the change touched. The assertions are the
 * exact written set and the rewritten targets, not a count: a phase that quietly
 * stopped rewriting a link would still write eight files.
 */
#[CoversClass(BuildSiteHandler::class)]
#[CoversClass(LinkRewriter::class)]
#[CoversClass(DocumentRoot::class)]
#[CoversClass(Tree::class)]
#[CoversClass(Markdown::class)]
#[CoversClass(Asset::class)]
#[CoversClass(BuildRecord::class)]
final class BuildCorpusTest extends TestCase
{
    private const string CORPUS = __DIR__ . '/../../docs/v1';

    #[Test]
    public function recordsALinkToAFileTheScanDidNotFindAndKeepsBuilding(): void
    {
        $source = $this->temporaryDirectory();
        $output = $this->temporaryDirectory();

        try {
            $this->copyCorpus($source);

            file_put_contents(
                filename: "{$source}/reference/cli.md",
                data    : "# CLI\n\nSee [nowhere](nowhere.md).\n",
            );

            $result = $this->build($source, $output);

            static::assertSame(MessageStatus::Failure, $result->getStatus());

            /** @var mixed $raw */
            $raw = $result->getResult();

            static::assertInstanceOf(BuildRecord::class, $raw);
            static::assertSame(['reference/cli.md'], array_keys($raw->failed));

            $reason = $raw->failed['reference/cli.md'];

            // Both sides of the link, because neither alone says what to fix.
            static::assertStringContainsString('reference/cli.md', $reason);
            static::assertStringContainsString('nowhere.md', $reason);

            // The failure is recorded rather than thrown, so the rest is written.
            static::assertFileExists("{$output}/index.html");
            static::assertFileExists("{$output}/concepts/document/index.html");
            static::assertFileDoesNotExist("{$output}/reference/cli/index.html");
        } finally {
            $this->removeDirectory($source);
            $this->removeDirectory($output);
        }
    }

    #[Test]
    public function rendersThroughTheViewLayerRatherThanReturningMarkdownHtml(): void
    {
        $output = $this->temporaryDirectory();

        try {
            $this->build(self::CORPUS, $output);

            $document = $this->read(
                directory: $output,
                relative : 'index.html',
            );

            // The layout shell, which only exists in the template.
            static::assertStringContainsString('<!DOCTYPE html>', $document);
            // The body template's own wrapper.
            static::assertStringContainsString('<main class="document">', $document);
            // Two always-on extensions, so the environment is the configured one.
            static::assertStringContainsString('class="heading-permalink"', $document);
            static::assertStringContainsString('table-of-contents', $document);
        } finally {
            $this->removeDirectory($output);
        }
    }

    #[Test]
    public function rewritesRelativeAbsoluteAndSuffixBearingTargets(): void
    {
        $output = $this->temporaryDirectory();

        try {
            $this->build(self::CORPUS, $output);

            $document = $this->read(
                directory: $output,
                relative : 'concepts/document/index.html',
            );

            // A target relative to the document's own directory, carrying a
            // fragment that survives the rewrite untouched. The fragment is
            // passed through as written, which does not match the id the
            // heading permalink emits; see the findings record.
            static::assertStringContainsString('href="/concepts/address/#the-index-rule"', $document);
            // A target that climbs out of that directory.
            static::assertStringContainsString('href="/getting-started/"', $document);

            $index = $this->read(
                directory: $output,
                relative : 'index.html',
            );

            // The index rule: a link naming `concepts/index.md` becomes the
            // directory, not `/concepts/index/`.
            static::assertStringContainsString('href="/concepts/"', $index);
            static::assertStringContainsString('href="/reference/cli/"', $index);

            $address = $this->read(
                directory: $output,
                relative : 'concepts/address/index.html',
            );

            // Two assets, addressed where they are copied.
            static::assertStringContainsString('href="/assets/site.css"', $address);
            static::assertStringContainsString('src="/assets/flow.svg"', $address);
        } finally {
            $this->removeDirectory($output);
        }
    }

    #[Test]
    public function writesTheCorpusAtTheAddressesItsSourcesImply(): void
    {
        $output = $this->temporaryDirectory();

        try {
            $result = $this->build(self::CORPUS, $output);

            static::assertSame(MessageStatus::Success, $result->getStatus());

            /** @var mixed $raw */
            $raw = $result->getResult();

            static::assertInstanceOf(BuildRecord::class, $raw);
            static::assertSame([], $raw->failed);
            static::assertSame(
                [
                    'assets/flow.svg',
                    'assets/site.css',
                    'concepts/address.md',
                    'concepts/document.md',
                    'concepts/index.md',
                    'getting-started.md',
                    'index.md',
                    'reference/cli.md',
                ],
                $raw->output,
            );

            static::assertSame(
                [
                    'assets/flow.svg',
                    'assets/site.css',
                    'concepts/address/index.html',
                    'concepts/document/index.html',
                    'concepts/index.html',
                    'getting-started/index.html',
                    'index.html',
                    'reference/cli/index.html',
                ],
                $this->written($output),
            );
        } finally {
            $this->removeDirectory($output);
        }
    }

    private function build(string $source, string $output): ResultInterface
    {
        /** @var MessageBusInterface $bus */
        $bus = $this->container()->get(MessageBusInterface::class);

        return $bus->handle(new BuildSiteCommand(
            source: $source,
            output: $output,
        ));
    }

    private function container(): ServiceManager
    {
        return require __DIR__ . '/../../config/container.php';
    }

    private function copyCorpus(string $destination): void
    {
        foreach ($this->written(self::CORPUS) as $relative) {
            $target = $destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $parent = dirname($target);

            if (! is_dir($parent)) {
                mkdir($parent, recursive: true);
            }

            copy(self::CORPUS . '/' . $relative, $target);
        }
    }

    private function read(string $directory, string $relative): string
    {
        $contents = file_get_contents(
            sprintf('%s/%s', $directory, $relative),
        );

        static::assertIsString($contents);

        return $contents;
    }

    private function removeDirectory(string $directory): void
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
            if ($entry->isDir()) {
                rmdir($entry->getPathname());

                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($directory);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'commondoc-corpus-' . bin2hex(random_bytes(8));

        mkdir($directory, recursive: true);

        return $directory;
    }

    /**
     * Every file beneath a directory, named relative to it and sorted.
     *
     * @return list<string>
     */
    private function written(string $directory): array
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $paths = [];

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            if (! $entry->isFile()) {
                continue;
            }

            $path = $entry->getPathname();
            $base = $directory . DIRECTORY_SEPARATOR;

            $paths[] = str_replace(
                search : DIRECTORY_SEPARATOR,
                replace: '/',
                subject: substr(
                    string: $path,
                    offset: strlen($base),
                ),
            );
        }

        sort($paths, SORT_STRING);

        return $paths;
    }
}
