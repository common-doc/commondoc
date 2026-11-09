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

use Laminas\ServiceManager\ServiceManager;

/** @var array<string, mixed> $config */
$config = require __DIR__ . '/config.php';

$dependencies                       = is_array($config['dependencies'] ?? null) ? $config['dependencies'] : [];
$dependencies['services']['config'] = $config;

return new ServiceManager($dependencies);
