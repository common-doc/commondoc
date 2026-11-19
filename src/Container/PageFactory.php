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

namespace CommonDoc\Container;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\Page;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_array;
use function is_string;

/**
 * Builds one page from the options its caller supplies.
 *
 * A page is per document, so it cannot be a shared service. The caller asks for
 * one with `build()`, which the service manager never caches, and passes only
 * what differs per document: its local path and its label. Everything shared by
 * every page in a deployment (the project name, the slug, the version label) is
 * read from config, and the address is derived from `DocumentRoot` rather than
 * accepted, so a page cannot carry a URL that disagrees with the file the write
 * pass produces for the same document.
 *
 * The section is keyed by `Page::class`, so a deployment states its identity in
 * its own autoload file. A missing or malformed section falls back to empty
 * strings rather than refusing: a page with no project name is a cosmetic
 * problem, while a container that cannot build its own services is not.
 *
 * @internal
 */
final readonly class PageFactory implements FactoryInterface
{
    /**
     * The two per-document options, or a typed refusal when either is absent.
     *
     * Read explicitly rather than through a shape, because `children` is
     * optional and its elements are checked by the constructor's own coercion.
     * A shape would have to describe them twice.
     *
     * @param ?array<array-key, mixed> $options
     *
     * @return array{localPath: non-empty-string, label: string, children: mixed}
     *
     * @throws InvalidArgumentException When the options do not name a document
     */
    private static function optionsFrom(?array $options): array
    {
        /** @var mixed $localPath Deliberately untyped: the checks below decide what it is */
        $localPath = $options['localPath'] ?? null;
        /** @var mixed $label Deliberately untyped: the checks below decide what it is */
        $label = $options['label'] ?? null;

        if (! is_string($localPath) || '' === $localPath || ! is_string($label)) {
            throw new InvalidArgumentException(
                'A page must be built with a "localPath" and a "label" option.',
            );
        }

        return [
            'localPath' => $localPath,
            'label'     => $label,
            'children'  => $options['children'] ?? [],
        ];
    }

    /**
     * The section this factory reads, or empty values when it is absent.
     *
     * @return array{projectName: string, projectSlug: string, versionLabel: string}
     *
     * @throws Type\Exception\ExceptionInterface When the section does not match the spec
     */
    private static function section(mixed $config): array
    {
        $defaults = [
            'projectName'  => '',
            'projectSlug'  => '',
            'versionLabel' => '',
        ];

        // Guarded rather than early-returned: a non-array config means there is
        // no section to read, and the shape check below falls back. One fallback
        // path instead of two, so no branch can be removed silently.
        /** @var mixed $section Deliberately untyped: the shape check decides what it is */
        $section = is_array($config) ? $config[Page::class] ?? null : null;

        $spec = Type\shape([
            'projectName'  => Type\string(),
            'projectSlug'  => Type\string(),
            'versionLabel' => Type\string(),
        ]);

        return $spec->matches($section) ? $spec->coerce($section) : $defaults;
    }

    /**
     * @param ?array<array-key, mixed> $options The page's local path, label and children
     *
     * @throws ContainerExceptionInterface When the document root cannot be fetched
     * @throws NotFoundExceptionInterface When the document root is not registered
     * @throws InvalidArgumentException When the options do not name a document
     * @throws Type\Exception\ExceptionInterface When a collaborator is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Page {
        $optionsFrom = self::optionsFrom($options);

        // Coerced rather than checked: an address cannot be derived without a
        // root, so a wrong service is a construction failure with a typed
        // message instead of a fatal error somewhere else.
        $root = Type\instance_of(DocumentRoot::class)->coerce($container->get(DocumentRoot::class));

        $section = self::section($container->get('config'));

        return new Page(
            label       : $optionsFrom['label'],
            projectName : $section['projectName'],
            projectSlug : $section['projectSlug'],
            sourcePath  : $optionsFrom['localPath'],
            url         : $root->published($optionsFrom['localPath']),
            versionLabel: $section['versionLabel'],
            children    : Type\vec(Type\instance_of(Page::class))->coerce($optionsFrom['children']),
        );
    }
}
