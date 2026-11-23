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
use CommonDoc\Output\ComponentIndex;
use CommonDoc\Output\DocumentRoute;
use CommonDoc\Output\VersionMenu;
use InvalidArgumentException as NativeInvalidArgumentException;
use Mezzio\Helper\Exception\RuntimeException as HelperException;
use Mezzio\Router\Exception\RuntimeException as RouterException;
use Mezzio\Template\Exception\ExceptionInterface as TemplateException;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Webware\FileWriter\Exception\ExceptionInterface as FileWriterException;
use Webware\FileWriter\FileWriter;
use Webware\MessageBus\MessageInterface;
use Webware\MessageBus\MiddlewareInterface;
use Webware\MessageBus\PipelineHandlerInterface;
use Webware\MessageBus\ResultInterface;

use function rtrim;
use function sprintf;

/**
 * Writes the pages that belong to no document: each component's own address, and the
 * site's home page.
 *
 * Both are derived from the roots, so they exist only when the site has versioned
 * components. A component's address redirects to its newest version, because a static
 * site has no server to do it. The home page is a card per component, and it is written
 * only when no document already publishes at the site root, so it never replaces one.
 *
 * It runs after the handler, because the URLs it writes are generated from the routes
 * the handler registers.
 *
 * @api
 */
final readonly class ComponentPageMiddleware implements MiddlewareInterface
{
    private const string INDEX = 'index.md';

    public function __construct(
        private DocumentRoute $route,
        private TemplateRendererInterface $renderer,
    ) {}

    /**
     * @throws InvalidArgumentException When a version's index is not a document path
     * @throws LogicException When the scan phase has not run
     * @throws HelperException When the pattern route cannot be generated from
     * @throws RouterException When the pattern route cannot be generated from
     * @throws NativeInvalidArgumentException When the helper refuses a malformed fragment. Nothing
     *         here passes a fragment, so this is declared rather than reachable
     * @throws FileWriterException When a page cannot be written
     * @throws TemplateException When the home page cannot be rendered
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
     * A static site cannot redirect itself, so the page does: a script replaces the
     * location before the first paint, a refresh covers a reader without scripting, and
     * the link is what is left when neither runs.
     *
     * It is rendered through the layout, so it carries the reader's theme. A page
     * outside the layout paints the browser's own canvas, which is white, and that
     * frame is what a reader in dark mode sees when navigating between components.
     *
     * @throws TemplateException When the page cannot be rendered
     * @throws FileWriterException When the page cannot be written
     */
    private function redirect(string $segment, string $url, string $output): void
    {
        FileWriter::writeTree(
            sprintf('%s/%s/index.html', rtrim($output, characters: '/\\'), $segment),
            $this->renderer->render(
                name  : 'commondoc::redirect',
                params: ['url' => $url],
            ),
        );
    }

    /**
     * @throws InvalidArgumentException When a version's index is not a document path
     * @throws LogicException When the scan phase has not run
     * @throws HelperException When the pattern route cannot be generated from
     * @throws RouterException When the pattern route cannot be generated from
     * @throws NativeInvalidArgumentException When the helper refuses a malformed fragment
     * @throws FileWriterException When a page cannot be written
     * @throws TemplateException When the home page cannot be rendered
     */
    private function write(BuildSiteCommand $command): void
    {
        $menu   = new VersionMenu($command->getRoots());
        $output = $command->getOutput();
        $cards  = [];

        foreach (new ComponentIndex($command->getRoots())->entries() as $entry) {
            $url = $this->route->url($entry['target']);

            if (null !== $entry['segment']) {
                $this->redirect($entry['segment'], $url, $output);
            }

            $cards[] = [
                'title'    => $entry['title'],
                'latest'   => $entry['latest'],
                'url'      => $url,
                'versions' => null === $entry['segment'] ? [] : $menu->links($entry['target'], $this->route),
            ];
        }

        $home = $this->route->file(localPath: self::INDEX);

        if ([] === $cards) {
            return;
        }

        // A document that publishes at the site root is the home page, so the cards would
        // replace it. Which name that document has is not decided here: `file()` folds every
        // directory index to the same output, so asking it covers README.md, index.md and
        // anything else the fold learns to accept.
        foreach ($command->getScan()->documents as $document) {
            if ($home === $this->route->file(localPath: $document)) {
                return;
            }
        }

        FileWriter::writeTree(
            sprintf('%s/%s', rtrim($output, characters: '/\\'), $home),
            $this->renderer->render('commondoc::home', ['cards' => $cards]),
        );
    }
}
