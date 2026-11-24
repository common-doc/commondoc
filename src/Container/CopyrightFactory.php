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

use CommonDoc\SiteConfig;
use CommonDoc\View\CopyrightNotice;
use CommonDoc\View\Helper\Copyright;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psl\Type;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Construction seam for the footer's copyright notice.
 *
 * @internal
 */
final readonly class CopyrightFactory implements FactoryInterface
{
    /**
     * @param ?array<array-key, mixed> $options
     *
     * @throws ContainerExceptionInterface When the site configuration cannot be fetched
     * @throws NotFoundExceptionInterface When the site configuration is not registered
     * @throws Type\Exception\ExceptionInterface When it is the wrong type
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Copyright {
        return new Copyright(
            site  : Type\instance_of(SiteConfig::class)->coerce($container->get(SiteConfig::class)),
            notice: new CopyrightNotice(),
        );
    }
}
