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

/*
 * Every option league/commonmark 2.x supports, held at its documented default.
 *
 * Nothing here is an override: the file states the supported surface, so a site
 * can change a value without having to discover the option names first.
 *
 * Defaults mirror Environment::createDefaultConfiguration() and the schema
 * contributed by CommonMarkCoreExtension.
 */

use League\CommonMark\Environment\EnvironmentInterface;

return [
    // Keyed by the environment interface so the section cannot collide with
    // any other package's config.
    EnvironmentInterface::class => [
        'renderer' => [
            'block_separator' => "\n",
            'inner_separator' => "\n",
            'soft_break'      => "\n",
        ],
        // One of 'allow', 'strip' or 'escape'.
        'html_input'              => 'allow',
        'allow_unsafe_links'      => true,
        'max_nesting_level'       => PHP_INT_MAX,
        'max_delimiters_per_line' => PHP_INT_MAX,
        'xml'                     => [
            'max_indentation_level' => 16,
        ],
        'slug_normalizer' => [
            'max_length' => 255,
            'unique'     => 'document',
            'reserved'   => [],
            // 'instance' is omitted: it takes a normalizer object, not a scalar.
        ],
        // The extension's own key name stays a literal string.
        'commonmark' => [
            'enable_em'              => true,
            'enable_strong'          => true,
            'use_asterisk'           => true,
            'use_underscore'         => true,
            'unordered_list_markers' => ['*', '+', '-'],
        ],
    ],
];
