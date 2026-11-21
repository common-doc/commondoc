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

use function htmlspecialchars;
use function in_array;
use function ltrim;
use function rtrim;
use function sprintf;

use const ENT_QUOTES;

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

    private const string REDIRECT =
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Redirecting</title>'
            . '<link rel="canonical" href="%s"><meta http-equiv="refresh" content="0; url=%s"></head>'
            . '<body><a href="%s">Latest version</a></body></html>';

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
     * A static site cannot redirect itself, so the page does: a refresh for browsers, a
     * canonical link for search engines, and a plain link for anything that does neither.
     *
     * @throws FileWriterException When the page cannot be written
     */
    private function redirect(string $segment, string $url, string $output): void
    {
        $escaped = htmlspecialchars(
            string: $url,
            flags : ENT_QUOTES,
        );

        FileWriter::writeTree(
            sprintf('%s/%s/index.html', rtrim($output, characters: '/\\'), $segment),
            sprintf(self::REDIRECT, $escaped, $escaped, $escaped),
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
        $latest = $menu->latest();
        $output = $command->getOutput();
        $cards  = [];

        foreach ($latest as $segment => $root) {
            $index = ltrim($root->mount, characters: '/') . self::INDEX;
            $url   = $this->route->url($index);

            $this->redirect($segment, $url, $output);

            $cards[] = [
                'title'    => $root->release?->component->label ?? $segment,
                'latest'   => $root->release?->version->tag ?? '',
                'url'      => $url,
                'versions' => $menu->links($index, $this->route),
            ];
        }

        $home = $this->route->file(localPath: self::INDEX);

        if ([] === $cards || in_array(self::INDEX, $command->getScan()->documents, strict: true)) {
            return;
        }

        FileWriter::writeTree(
            sprintf('%s/%s', rtrim($output, characters: '/\\'), $home),
            $this->renderer->render('commondoc::home', ['cards' => $cards]),
        );
    }
}
