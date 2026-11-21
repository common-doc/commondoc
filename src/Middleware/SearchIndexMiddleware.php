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

namespace CommonDoc\Middleware;

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Exception\LogicException;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\Output\NavigationScope;
use CommonDoc\Output\SearchIndex;
use InvalidArgumentException as NativeInvalidArgumentException;
use Mezzio\Helper\Exception\RuntimeException as HelperException;
use Mezzio\Router\Exception\RuntimeException as RouterException;
use Override;
use Psl\Async;
use Psl\File;
use Psl\Filesystem;
use Psl\Json;
use Psl\Regex;
use Psl\Type;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;
use Webware\FileWriter\FileWriter;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MiddlewareInterface;
use Webware\MessageBus\PipelineHandlerInterface;
use Webware\MessageBus\ResultInterface;

use function rtrim;
use function sprintf;
use function str_contains;
use function strlen;
use function substr;
use function trim;

/**
 * Writes the site's search index, from the pages the build just wrote.
 *
 * It runs after the handler and reads each document's published file, so what is searchable
 * is exactly what was published: a document that failed to render has no file and is not
 * indexed. One index covers the whole site, and every entry says which component and version
 * it belongs to so a result can be told apart from a page of the same name elsewhere.
 *
 * @api
 */
final readonly class SearchIndexMiddleware implements MiddlewareInterface
{
    public const string FILE = 'search-index.json';

    public function __construct(
        private DocumentRoute $route,
        private SearchIndex $index = new SearchIndex(),
    ) {}

    /**
     * A scope's mount as a short label, such as `webware-log/v2`.
     */
    private static function section(string $scope): string
    {
        return str_contains($scope, ':')
            ? trim(substr($scope, strlen(NavigationScope::BASE) + 1), characters: '/')
            : '';
    }

    /**
     * @throws InvalidArgumentException When a scanned path is not a document path
     * @throws LogicException When the scan phase has not run
     * @throws HelperException When the pattern route cannot be generated from
     * @throws RouterException When the pattern route cannot be generated from
     * @throws NativeInvalidArgumentException When the helper refuses a malformed fragment
     * @throws FileWriterException When the index cannot be written
     * @throws File\Exception\ExceptionInterface When a written page cannot be read
     * @throws Json\Exception\EncodeException When the index cannot be encoded
     * @throws Async\Exception\CancelledException When reading a page is cancelled
     * @throws Regex\Exception\ExceptionInterface When a fixed pattern cannot be applied, which it cannot
     * @throws Type\Exception\ExceptionInterface When a heading is not the shape its pattern promises
     */
    #[Override]
    public function process(MessageInterface $message, PipelineHandlerInterface $next): ResultInterface
    {
        $result = $next->handle($message);

        if ($message instanceof BuildSiteCommand) {
            $this->write($message);
        }

        return $result;
    }

    /**
     * @throws InvalidArgumentException When a scanned path is not a document path
     * @throws LogicException When the scan phase has not run
     * @throws HelperException When the pattern route cannot be generated from
     * @throws RouterException When the pattern route cannot be generated from
     * @throws NativeInvalidArgumentException When the helper refuses a malformed fragment
     * @throws FileWriterException When the index cannot be written
     * @throws File\Exception\ExceptionInterface When a written page cannot be read
     * @throws Json\Exception\EncodeException When the index cannot be encoded
     * @throws Async\Exception\CancelledException When reading a page is cancelled
     * @throws Regex\Exception\ExceptionInterface When a fixed pattern cannot be applied, which it cannot
     * @throws Type\Exception\ExceptionInterface When a heading is not the shape its pattern promises
     */
    private function write(BuildSiteCommand $command): void
    {
        $output = rtrim($command->getOutput(), characters: '/\\');
        $scope  = new NavigationScope($command->getRoots());
        $pages  = [];

        foreach ($command->getScan()->documents as $document) {
            $file = sprintf('%s/%s', $output, $this->route->file($document));

            if (! Filesystem\is_file($file)) {
                continue;
            }

            $entry = $this->index->entry(
                html   : File\read($file),
                url    : $this->route->url($document),
                section: self::section($scope->of($document)),
            );

            if (null !== $entry) {
                $pages[] = $entry;
            }
        }

        FileWriter::writeTree(
            sprintf('%s/%s', $output, self::FILE),
            Json\encode(['version' => 1, 'pages' => $pages]),
        );
    }
}
