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

namespace CommonDoc\Output;

use CommonDoc\DocumentData;
use CommonDoc\Exception\RuntimeException;
use CommonDoc\View\Page;
use Dflydev\DotAccessData\Exception\InvalidPathException;
use InvalidArgumentException;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\AbstractWebResource;
use Mezzio\Helper\Exception\RuntimeException as HelperException;
use Mezzio\Helper\UrlHelper;

use function array_fill_keys;
use function array_key_exists;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strcspn;
use function substr;

/**
 * Rewrites document links and image references to published paths.
 *
 * A Markdown source refers to its neighbours the way a file system does, so a
 * link that is correct in the repository is wrong on a published site. This runs
 * on the parsed tree rather than on finished HTML, because a parsed link has a
 * known target while a string replacement would also rewrite prose that happens
 * to look like a path, and attribute values it was never meant to touch.
 *
 * It reads the same translation the write pass uses, so a path in a link and the
 * file that serves it are produced by one code path and cannot disagree.
 *
 * Three collaborators, each answering one question:
 *
 * - `DocumentRoot` says what a local path is published at. It is a pure
 *   function, so it needs no table and no cache to be asked once per link.
 * - The two lists say whether a target exists, and which of the two translations
 *   applies. Recomputation can produce a path for anything; only the discovered
 *   sets can say the target is real, and a link to a document that does not
 *   exist has to fail the build rather than publish a dead link.
 * - The document being rewritten supplies its own path, because a relative
 *   target only means something against the document it was written in. That
 *   path arrives on the document's data, written by `PageContextListener`
 *   before this listener runs, so nothing here changes between documents and
 *   the whole type can be exercised with a bare `Document`.
 *
 * @api
 */
final readonly class LinkRewriter
{
    /** The shape of a scheme, which is what makes a target external. */
    private const string SCHEME = '~^[A-Za-z][A-Za-z0-9+.\-]*:~';

    /** The characters that end the path part of a target. */
    private const string SUFFIX_START = '#?';

    /** @var array<non-empty-string, true> */
    private array $documents;

    /** @var array<non-empty-string, true> */
    private array $assets;

    /**
     * @param list<non-empty-string> $documents Every document the scan discovered
     * @param list<non-empty-string> $assets    Every path that is not a document
     */
    public function __construct(
        private DocumentRoot $root,
        private UrlHelper $url,
        array $documents,
        array $assets,
    ) {
        $this->documents = array_fill_keys(
            keys : $documents,
            value: true,
        );
        $this->assets = array_fill_keys(
            keys : $assets,
            value: true,
        );
    }

    /**
     * The repository URL a target names, or null when the page has no repository
     * or the target stays inside it.
     *
     * The climb is counted in repository space, which is why the page carries both the
     * source it was read from and its own path from the repository root: a site path has
     * already spent its mount and cannot say how far a target climbed from the document
     * that wrote it. The scan recorded both while walking, so nothing here derives either
     * by subtraction.
     */
    private function aboveSite(string $path, Page $page): ?string
    {
        $from = $page->repositoryPath;

        if (null === $from) {
            return null;
        }

        return $page->source?->link($path, $from);
    }

    /**
     * The published path a single target has on the site.
     *
     * @param string $target The destination exactly as the source wrote it
     * @param Page   $page   The document holding the link, which supplies both the path
     *                       the target resolves against and the source it came from
     *
     * @throws InvalidArgumentException when the translation refuses a path,
     *         which the membership check above makes unreachable. It is declared
     *         because the translation declares it, and a caller should not have
     *         to reason about which of the two calls could raise it.
     * @throws HelperException when a document the scan found has no route. That is a
     *         defect in this package rather than in the documentation, so it is not
     *         treated as a dead link and does not fall back to the source
     * @throws RuntimeException when the target resolves to nothing that exists
     */
    private function rewrite(string $target, Page $page): string
    {
        if (
            '' === $target
            || str_starts_with($target, '//')
            || preg_match(self::SCHEME, $target) === 1
        ) {
            return $target;
        }

        $position = strcspn($target, self::SUFFIX_START);
        $path     = substr(
            string: $target,
            offset: 0,
            length: $position,
        );
        $suffix = substr($target, $position);

        if ('' === $path) {
            return $target;
        }

        try {
            return $this->servedAt($path, $target, $page) . $suffix;
        } catch (RuntimeException $refused) {
            // Where the target still resolves inside the repository the root came
            // from, it is a link to the source: a document may point at a directory
            // or a file it does not publish, and refusing that would make a
            // repository's own documentation unpublishable without editing it.
            $above = $this->aboveSite($path, $page);

            if (null === $above) {
                throw $refused;
            }

            return $above . $suffix;
        }
    }

    /**
     * The published path the build serves a target at.
     *
     * Recomputation can produce a path for anything, so only the discovered sets can
     * say the target is real. A target that resolves to nothing is refused here rather
     * than reported as missing later, because a link to a file that does not exist is
     * a build failure and not something to publish.
     *
     * @param string $path   The target with its query and fragment removed
     * @param string $target The target exactly as the document wrote it, for the refusal
     * @param Page   $page   The document holding the link
     *
     * @throws InvalidArgumentException from the translation, which the membership
     *         check makes unreachable; it is declared because the translation declares it
     * @throws HelperException from the URL helper when the candidate has no route. That
     *         is the same "found nothing" case the refusal below reports, raised by the
     *         router instead of by this type
     * @throws RuntimeException when the target resolves to nothing the scan found, or
     *         climbs above the site root
     */
    private function servedAt(string $path, string $target, Page $page): string
    {
        $candidate = SourcePath::resolve($path, $page->sourcePath);

        if (array_key_exists($candidate, $this->documents)) {
            // A document is a route, so its URL comes from the router. The route was
            // registered at the document's published path and carries no name of its own,
            // so the published path is also the name to generate from. This is the only
            // place a document's address is produced, so a link and the page it targets
            // cannot disagree.
            return $this->url->generate(routeName: $this->root->published($candidate));
        }

        if (array_key_exists($candidate, $this->assets)) {
            // An asset is not a route: it is copied where it sits, so its address is the
            // base path followed by its own path.
            return $this->root->asset($candidate);
        }

        throw new RuntimeException(sprintf(
            'The document "%s" links to "%s", which resolves to "%s" and is neither a document nor an asset the scan found.',
            $page->sourcePath,
            $target,
            $candidate,
        ));
    }

    /**
     * Rewrites every link and image in the document that is being built.
     *
     * A document with no page is left alone rather than refused: the same
     * listener serves a preview render and a build, and only a build knows which
     * document a relative target belongs to.
     *
     * @throws InvalidArgumentException when the translation refuses a path, which
     *         the membership check in `rewrite()` makes unreachable. This is the
     *         package's own type, so it does not cover a vendor exception; the
     *         data read below is therefore declared separately.
     * @throws HelperException when a document the scan found has no route, which is
     *         a defect in this package rather than a dead link
     * @throws InvalidPathException from the document data read, which the default
     *         value makes unreachable in practice
     * @throws RuntimeException when a target resolves to nothing the scan found,
     *         or to a path above the source root
     */
    public function __invoke(DocumentParsedEvent $event): void
    {
        $document = $event->getDocument();

        /** @var mixed $page */
        $page = $document->data->get(DocumentData::Page->value, default: null);

        if (! $page instanceof Page) {
            return;
        }

        foreach ($document->iterator() as $node) {
            if (! $node instanceof AbstractWebResource) {
                continue;
            }

            $node->setUrl($this->rewrite($node->getUrl(), $page));
        }
    }
}
