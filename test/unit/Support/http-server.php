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

/**
 * Starts the one-request server for `LoopbackHttpServer`. Not a test, and never autoloaded.
 *
 * A script rather than a test class because it is a program: it is started with `php`, answers
 * once, and exits. It needs the autoloader only to reach the fixture class.
 */

use CommonDocTest\Support\HttpServerFixture;

require dirname(__DIR__, levels: 3) . '/vendor/autoload.php';

exit(HttpServerFixture::run($argv));
