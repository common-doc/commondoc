<?php

declare(strict_types=1);

namespace CommonDoc\Output;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Markdown;
use Psl\Str;

use function explode;
use function in_array;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * A local source root mounted at a URL prefix.
 *
 * The same relationship a web server has with a document root: a directory on
 * disk serves every file beneath it at exactly one published path, and that path
 * is the file's own path with the root stripped and the suffix mapped. Nothing
 * else is involved, and no host name is needed, because a path that begins with
 * a slash is complete without one.
 *
 * This is the single translation. The write pass asks it where a document's file
 * goes, and the render pass asks it what path a link target has. Asking one type
 * twice is what keeps a file and its published path from drifting apart.
 *
 * The two answers are not symmetric, and that asymmetry is the point: the
 * published path carries the base path, because that is how the site is reached,
 * while the file is relative to the output directory, because that directory is
 * mounted at the base path rather than containing it.
 *
 * @api
 */
final readonly class DocumentRoot
{
    private const string DOCUMENT_PATTERN = '~^[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*$~';

    private const string INDEX = 'index';

    /** The segment `directoryOf()` strips, spelled once so no offset is counted by hand. */
    private const string NESTED_INDEX = '/index';

    /** @var non-empty-string */
    public string $basePath;

    /**
     * @param string $basePath The prefix this root is mounted at, such as `/` or `/repo`
     *
     * @throws InvalidArgumentException When the base path cannot prefix a path
     */
    public function __construct(
        string $basePath,
        public PathType $pathType = PathType::Directory,
    ) {
        $this->basePath = self::prefix($basePath);
    }

    /**
     * The directory a document is served from, relative to the root.
     *
     * A document named `index` is the index of its own directory rather than a
     * directory of its own, so `guide/index.md` becomes `guide` and not
     * `guide/index`. That single rule is what keeps a published path and the file
     * serving it in agreement.
     */
    private static function directoryOf(string $relative): string
    {
        if (self::INDEX === $relative) {
            return '';
        }

        return str_ends_with($relative, self::NESTED_INDEX)
            ? substr(
                string: $relative,
                offset: 0,
                length: -strlen(self::NESTED_INDEX),
            )
            : $relative;
    }

    /**
     * The base path with exactly one trailing slash, so a concatenation cannot
     * produce a doubled or missing separator.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When the value cannot prefix a path
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
     * The local path with its document suffix removed.
     *
     * The suffix is asked of `Markdown`, which is the only type that decides what
     * a document is, so the translation and the scan cannot disagree. The strip
     * length comes from that same constant rather than a counted literal, which
     * would be a third copy of the rule hiding in an offset.
     *
     * The pattern deliberately does not mention the suffix. It bounds the
     * characters a path may contain, and the suffix is a separate question with
     * a separate owner.
     *
     * @throws InvalidArgumentException When the path is not a relative markdown path
     */
    private static function relativeOf(string $localPath): string
    {
        if (
            ! Str\ends_with_ci($localPath, Markdown::EXTENSION)
            || preg_match(self::DOCUMENT_PATTERN, $localPath) !== 1
        ) {
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
            length: -strlen(Markdown::EXTENSION),
        );
    }

    /**
     * The path an asset is published at, such as `/images/diagram.png`.
     *
     * An asset is not translated: it is copied where it sits, so its published path
     * is the base path followed by its own path. That is the whole difference from
     * a document, whose suffix and index name are implied by the path rather
     * than spelled in it. Keeping the two answers here means a link to an image
     * and the file that serves it come from one place, exactly as they do for a
     * document.
     *
     * The output file is deliberately not derived here. An asset is written at
     * its own local path, relative to the output directory, so there is nothing
     * to translate and a second method would be a no-op with a name.
     *
     * @param string $localPath A path relative to the root, as the scan produced it
     *
     * @return non-empty-string
     */
    public function asset(string $localPath): string
    {
        return $this->basePath . $localPath;
    }

    /**
     * The file a local path is written to, relative to the output directory.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When the local path does not name a document
     */
    public function file(string $localPath): string
    {
        $relative = self::relativeOf($localPath);

        if (PathType::File === $this->pathType) {
            return "{$relative}.html";
        }

        $directory = self::directoryOf($relative);

        return '' === $directory ? self::INDEX . '.html' : "{$directory}/" . self::INDEX . '.html';
    }

    /**
     * The path a local path is published at, such as `/guide/setup/`.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When the local path does not name a document
     */
    public function published(string $localPath): string
    {
        $relative = self::relativeOf($localPath);

        if (PathType::File === $this->pathType) {
            return $this->basePath . "{$relative}.html";
        }

        $directory = self::directoryOf($relative);

        return '' === $directory ? $this->basePath : "{$this->basePath}{$directory}/";
    }
}
