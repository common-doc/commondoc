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

namespace CommonDoc\Output;

use Psl\Regex;
use Psl\Type;

use function html_entity_decode;
use function strip_tags;
use function strpos;
use function strrpos;
use function substr;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * What the search needs to know about one written page, read from its HTML.
 *
 * The page is read as the reader receives it rather than from the converter, so the index
 * describes exactly what was published and a document that failed to render has nothing to
 * index. Only the document body is read: the sidebar and the version switcher are on every
 * page and would make every page match every component name.
 *
 * The permalink anchors and the table of contents CommonMark adds are removed first, because
 * they repeat the headings and would score every heading twice.
 *
 * @type Heading = array{id: string, title: string, level: int}
 * @type Entry = array{url: string, section: string, title: string, headings: list<Heading>, text: string}
 *
 * @api
 */
final readonly class SearchIndex
{
    private const string BODY_START = '<main class="document">';

    private const string BODY_END = '</main>';

    /**
     * The visible text of a fragment: tags gone, entities decoded, whitespace collapsed.
     *
     * A space is put before every tag first, so two words either side of one are not joined.
     *
     * @throws Regex\Exception\ExceptionInterface When a fixed pattern cannot be applied, which it cannot
     */
    private static function plain(string $html): string
    {
        $spaced = Regex\replace(
            haystack   : $html,
            pattern    : '~<~',
            replacement: ' <',
        );
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5);

        return trim(Regex\replace(
            haystack   : $text,
            pattern    : '~\s+~u',
            replacement: ' ',
        ));
    }

    /**
     * @return ?Entry Null when the page has no document body
     *
     * @throws Regex\Exception\ExceptionInterface When a fixed pattern cannot be applied, which it cannot
     * @throws Type\Exception\ExceptionInterface When a heading match is not the shape the pattern promises
     */
    public function entry(string $html, string $url, string $section): ?array
    {
        $start = strpos($html, self::BODY_START);
        $end   = strrpos($html, self::BODY_END);

        if (false === $start || false === $end || $end < $start) {
            return null;
        }

        $body = substr($html, $start, $end - $start);
        $body = Regex\replace(
            haystack   : $body,
            pattern    : '~<a\s[^>]*class="heading-permalink"[^>]*>.*?</a>~s',
            replacement: '',
        );
        $body = Regex\replace(
            haystack   : $body,
            pattern    : '~<ul class="table-of-contents">.*?</ul>~s',
            replacement: '',
        );

        $headings = [];

        $matches = Regex\every_match(
            subject      : $body,
            pattern      : '~<h([1-6])\s+id="([^"]*)"[^>]*>(.*?)</h\1>~s',
            captureGroups: Type\shape([1 => Type\string(), 2 => Type\string(), 3 => Type\string()]),
        );

        foreach ($matches ?? [] as $match) {
            $title = self::plain($match[3]);

            if ('' === $title) {
                continue;
            }

            $headings[] = ['id' => $match[2], 'title' => $title, 'level' => (int) $match[1]];
        }

        $first = $headings[0] ?? null;

        return [
            'url'      => $url,
            'section'  => $section,
            'title'    => null !== $first && 1 === $first['level'] ? $first['title'] : $url,
            'headings' => $headings,
            'text'     => self::plain($body),
        ];
    }
}
