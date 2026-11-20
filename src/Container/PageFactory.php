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
use CommonDoc\SiteConfig;
use CommonDoc\View\Page;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_string;

/**
 * Builds one page from the options its caller supplies.
 *
 * A page is per document, so it cannot be a shared service. The caller asks for
 * one with `build()`, which the service manager never caches, and passes only
 * what differs per document: its local path and its label. Everything shared by
 * every page in a deployment (the project name, the slug, the version label) comes
 * from `SiteConfig`, and the published path is derived from the translation that site
 * carries rather than accepted, so a page cannot carry a URL that disagrees with
 * the file the write pass produces for the same document.
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
     * @param ?array<array-key, mixed> $options The page's local path, label and children
     *
     * @throws ContainerExceptionInterface When a collaborator cannot be fetched
     * @throws NotFoundExceptionInterface When a collaborator is not registered
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

        // The site carries the translation, so it is read once here rather than
        // fetched again: a second fetch could only return the same object, and a
        // second source for a path is a second rule.
        $site = Type\instance_of(SiteConfig::class)->coerce($container->get(SiteConfig::class));

        return new Page(
            label       : $optionsFrom['label'],
            projectName : $site->projectName,
            projectSlug : $site->projectSlug,
            sourcePath  : $optionsFrom['localPath'],
            url         : $site->documentRoot->published($optionsFrom['localPath']),
            versionLabel: $site->versionLabel,
            children    : Type\vec(Type\instance_of(Page::class))->coerce($optionsFrom['children']),
        );
    }
}
