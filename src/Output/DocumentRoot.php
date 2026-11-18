<?php

declare(strict_types=1);

namespace CommonDoc\Output;

use InvalidArgumentException;

use function explode;
use function in_array;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function substr;

/**
 * A local source root mounted at a URL prefix.
 *
 * The same relationship a web server has with a document root: a directory on
 * disk serves every file beneath it at exactly one address, and the address is
 * the file's path with the root stripped and the suffix mapped. Nothing else is
 * involved, and no host name is needed, because an address that begins with a
 * slash is complete without one.
 *
 * This is the single translation. The write pass asks it where a document's file
 * goes, and the render pass asks it what address a link target has. Asking one
 * type twice is what keeps a file and its address from drifting apart.
 *
 * The two answers are not symmetric, and that asymmetry is the point: the
 * published address carries the base path, because that is how the site is
 * reached, while the file is relative to the output directory, because that
 * directory is mounted at the base path rather than containing it.
 *
 * @api
 */
final readonly class DocumentRoot
{
    private const string DOCUMENT_PATTERN = '~^[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*\.md$~';

    private const string INDEX = 'index';

    /** @var non-empty-string */
    public string $basePath;

    /**
     * @param string $basePath The prefix this root is mounted at, such as `/` or `/repo`
     *
     * @throws InvalidArgumentException When the base path cannot prefix an address
     */
    public function __construct(
        string $basePath,
        public Style $style = Style::Directory,
    ) {
        $this->basePath = self::prefix($basePath);
    }

    /**
     * The directory a document is served from, relative to the root.
     *
     * A document named `index` is the index of its own directory rather than a
     * directory of its own, so `guide/index.md` becomes `guide` and not
     * `guide/index`. That single rule is what keeps an address and the file
     * serving it in agreement.
     */
    private static function directoryOf(string $relative): string
    {
        if ('' === $relative || self::INDEX === $relative) {
            return '';
        }

        return str_ends_with($relative, '/' . self::INDEX)
            ? substr(
                string: $relative,
                offset: 0,
                length: -6,
            )
            : $relative;
    }

    /**
     * The base path with exactly one trailing slash, so a concatenation cannot
     * produce a doubled or missing separator.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When the value cannot prefix an address
     */
    private static function prefix(string $basePath): string
    {
        if ('' === $basePath || ! str_starts_with($basePath, '/')) {
            throw new InvalidArgumentException(sprintf(
                'The base path "%s" must start with a slash.',
                $basePath,
            ));
        }

        if (str_ends_with($basePath, '//')) {
            throw new InvalidArgumentException(sprintf(
                'The base path "%s" carries more than one trailing slash.',
                $basePath,
            ));
        }

        return str_ends_with($basePath, '/') ? $basePath : "{$basePath}/";
    }

    /**
     * The local path with its markdown suffix removed.
     *
     * The suffix is removed once, here, so the address and the file cannot map a
     * document differently.
     *
     * @throws InvalidArgumentException When the path is not a relative markdown path
     */
    private static function relativeOf(string $localPath): string
    {
        if (preg_match(self::DOCUMENT_PATTERN, $localPath) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'The local path "%s" is not a relative markdown path.',
                $localPath,
            ));
        }

        if (in_array('..', explode('/', $localPath), strict: true)) {
            throw new InvalidArgumentException(sprintf(
                'The local path "%s" leaves the document root.',
                $localPath,
            ));
        }

        return substr(
            string: $localPath,
            offset: 0,
            length: -3,
        );
    }

    /**
     * The file a local path is written to, relative to the output directory.
     *
     * @throws InvalidArgumentException When the local path does not name a document
     */
    public function file(string $localPath): string
    {
        $relative = self::relativeOf($localPath);

        if (Style::Plain === $this->style) {
            return '' === $relative ? self::INDEX . '.html' : "{$relative}.html";
        }

        $directory = self::directoryOf($relative);

        return '' === $directory ? self::INDEX . '.html' : "{$directory}/" . self::INDEX . '.html';
    }

    /**
     * The address a local path is published at, such as `/guide/setup/`.
     *
     * @throws InvalidArgumentException When the local path does not name a document
     */
    public function published(string $localPath): string
    {
        $relative = self::relativeOf($localPath);

        if (Style::Plain === $this->style) {
            return $this->basePath . ('' === $relative ? self::INDEX . '.html' : "{$relative}.html");
        }

        $directory = self::directoryOf($relative);

        return '' === $directory ? $this->basePath : "{$this->basePath}{$directory}/";
    }
}
