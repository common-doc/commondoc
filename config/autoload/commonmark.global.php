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
 * Every option league/commonmark 2.x supports, held at its documented default,
 * with one deliberate override: `allow_unsafe_links` is false, so `javascript:`
 * and `data:` targets are refused.
 *
 * The seven extension blocks below belong to the extensions declared in
 * CommonDoc\ConfigProvider::getExtensionConfig(), and all of them sit at their
 * documented default. That combination is the locked definition of safe mode:
 * allow raw HTML, filter the nine GFM tagfilter tags, refuse unsafe links. Do
 * not switch `html_input` to 'escape'; that is a different, stricter mode.
 *
 * Defaults mirror Environment::createDefaultConfiguration() and the schemas
 * contributed by CommonMarkCoreExtension and the registered extensions.
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
        'allow_unsafe_links'      => false,
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
        'table' => [
            'wrap' => [
                'enabled'    => false,
                'tag'        => 'div',
                'attributes' => [],
            ],
            'alignment_attributes' => [
                'left'   => ['align' => 'left'],
                'center' => ['align' => 'center'],
                'right'  => ['align' => 'right'],
            ],
            // TableParser::DEFAULT_MAX_AUTOCOMPLETED_CELLS.
            'max_autocompleted_cells' => 10_000,
        ],
        'heading_permalink' => [
            'min_heading_level'   => 1,
            'max_heading_level'   => 6,
            // One of 'before', 'after' or 'none'.
            'insert'              => 'before',
            'id_prefix'           => 'content',
            'apply_id_to_heading' => false,
            'heading_class'       => '',
            'fragment_prefix'     => 'content',
            'html_class'          => 'heading-permalink',
            'title'               => 'Permalink',
            // HeadingPermalinkRenderer::DEFAULT_SYMBOL, the pilcrow.
            'symbol'              => "\u{00B6}",
            'aria_hidden'         => true,
        ],
        'table_of_contents' => [
            // One of 'top', 'placeholder' or 'before-headings'.
            'position'                => 'top',
            // One of 'bullet' or 'ordered'.
            'style'                   => 'bullet',
            // One of 'relative', 'flat' or 'as-is'.
            'normalize'               => 'relative',
            'min_heading_level'       => 1,
            'max_heading_level'       => 6,
            'html_class'              => 'table-of-contents',
            'placeholder'             => null,
            'max_placeholder_entries' => null,
        ],
        'normalize_headings' => [
            'min_level'           => 1,
            'max_level'           => 6,
            'rebase_to_min_level' => false,
        ],
        'external_link' => [
            // Empty means no host is internal, so every absolute link is external.
            'internal_hosts'     => [],
            'open_in_new_window' => false,
            'html_class'         => '',
            // Each of these takes 'none', 'all', 'internal' or 'external'.
            'nofollow'           => 'none',
            'noopener'           => 'external',
            'noreferrer'         => 'external',
        ],
        'autolink' => [
            'allowed_protocols' => ['http', 'https', 'ftp'],
            'default_protocol'  => 'http',
        ],
        'disallowed_raw_html' => [
            // GFM's tagfilter list, which is the extension's own default.
            'disallowed_tags' => [
                'title',
                'textarea',
                'style',
                'xmp',
                'iframe',
                'noembed',
                'noframes',
                'script',
                'plaintext',
            ],
        ],
    ],
];
