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

use CommonDoc\Output\DocumentRoot;
use CommonDoc\View\Page;
use InvalidArgumentException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_array;

/**
 * Produces the callable that builds a page.
 *
 * A page cannot be a container service, because there is one per document and
 * the container builds a service once. What the container can hold is the thing
 * that makes them, so this factory returns a callable rather than a page, the
 * same shape the user manager's `UserFactory` uses for the same reason.
 *
 * The point of the callable is that the configuration is read once, here, and
 * captured. A page's identity is deployment data: every page in a build shares
 * one project name, one slug and one version label, and none of that belongs in
 * the argument list at the call site. The call site supplies only what differs
 * per document, which is its local path and its label.
 *
 * The address is derived rather than accepted. `DocumentRoot` owns the
 * translation, so a page cannot be given a URL that disagrees with the file the
 * write pass produces for the same document. That is the whole reason the page
 * is built here instead of assembled by hand.
 *
 * The section is keyed by `Page::class`, so a deployment states its identity in
 * its own autoload file. A missing or malformed section falls back to empty
 * strings rather than refusing: a page with no project name is a cosmetic
 * problem, while a container that cannot build its own services is not.
 *
 * @internal
 */
final readonly class PageFactoryFactory implements FactoryInterface
{
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
     * @param ?array<array-key, mixed> $options
     *
     * @throws ContainerExceptionInterface When the document root cannot be fetched
     * @throws NotFoundExceptionInterface When the document root is not registered
     * @throws Type\Exception\ExceptionInterface When the root or the section does not match its spec
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): callable {
        // Coerced rather than checked: an address cannot be derived without a
        // root, so a wrong service is a construction failure with a typed
        // message instead of a fatal error at the first call.
        $root = Type\instance_of(DocumentRoot::class)->coerce($container->get(DocumentRoot::class));

        $section      = self::section($container->get('config'));
        $projectName  = $section['projectName'];
        $projectSlug  = $section['projectSlug'];
        $versionLabel = $section['versionLabel'];

        /**
         * Build one page for one document.
         *
         * Declared here rather than inline so the exceptions it can raise are
         * part of its type: a page cannot be built without an address, and an
         * address cannot be derived from a path that is not a document.
         *
         * @param mixed $children Anything `Psl\Type` can read as a page list
         *
         * @throws InvalidArgumentException When the local path does not name a document
         * @throws Type\Exception\ExceptionInterface When a child is not a page
         */
        // @mago-expect lint:inline-variable-return - the assignment is what
        // carries the docblock declaring the throws; inlining it would drop them.
        $factory = static fn(
            string $localPath,
            string $label,
            mixed $children = [],
        ): Page => new Page(
            label       : $label,
            projectName : $projectName,
            projectSlug : $projectSlug,
            sourcePath  : $localPath,
            url         : $root->published($localPath),
            versionLabel: $versionLabel,
            children    : Type\vec(Type\instance_of(Page::class))->coerce($children),
        );

        return $factory;
    }
}
