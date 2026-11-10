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

use CommonDoc\ConfigProvider;
use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Laminas\Hydrator\ConfigProvider as HydratorProvider;
use Laminas\View\ConfigProvider as ViewProvider;
use Mezzio\Helper\ConfigProvider as HelperProvider;
use Mezzio\LaminasView\ConfigProvider as LaminasViewProvider;
use Phly\EventDispatcher\ConfigProvider as EventDispatcherProvider;
use Webware\Console\ConfigProvider as ConsoleProvider;
use Webware\Event\ConfigProvider as EventProvider;
use Webware\MessageBus\ConfigProvider as BusProvider;
use Webware\MessageBus\Event\ConfigProvider as BusEventProvider;
use Webware\Router\ConfigProvider as RouterProvider;
use Webware\Theme\ConfigProvider as ThemeProvider;

// To enable or disable caching, set the `ConfigAggregator::ENABLE_CACHE` boolean in
// `config/autoload/local.php`.
$cacheConfig = [
    'config_cache_path' => 'data/cache/config-cache.php',
];

$aggregator = new ConfigAggregator(
    [
        // Third party providers are aggregated first so that the application
        // providers below win wherever the two overlap.
        // webware-theme has to be aggregated after the Laminas view provider: both
        // own AggregateResolver::class, the later provider wins, and the theme has
        // to be the resolver consulted first.
        LaminasViewProvider::class,
        ThemeProvider::class,
        HydratorProvider::class,
        HelperProvider::class,
        // The event dispatcher has to be aggregated before webware-event: the latter
        // aliases EventDispatcherInterface to it but ships no factory for it.
        EventDispatcherProvider::class,
        EventProvider::class,
        BusProvider::class,
        BusEventProvider::class,
        ConsoleProvider::class,
        ViewProvider::class,
        RouterProvider::class,
        ConfigProvider::class,
        // Include cache configuration.
        new ArrayProvider($cacheConfig),
        // Load application config in a pre-defined order so that local settings
        // overwrite global settings. (Loaded first to last):
        //   - `global.php`
        //   - `*.global.php`
        //   - `local.php`
        //   - `*.local.php`
        new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
        // Load development config if it exists.
        new PhpFileProvider(realpath(__DIR__) . '/development.config.php'),
    ],
    $cacheConfig['config_cache_path'],
);

return $aggregator->getMergedConfig();
