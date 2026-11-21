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

namespace CommonDoc\Extension\Admonition;

use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use Psl\Regex;
use Psl\Type;

use function array_map;
use function implode;
use function strtolower;

/**
 * The `[!NOTE]` line a paragraph opens with, found in the parsed tree.
 *
 * An unresolved `[!NOTE]` can arrive as several adjacent text nodes, so the run is read
 * together. It has to stand alone on its line: any other inline after it, such as more text
 * or emphasis, means this is not a marker.
 *
 * @internal
 */
final readonly class AdmonitionMarker
{
    private const string PATTERN = '~^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]$~i';

    /**
     * @param list<Text> $texts The text nodes that spell the marker
     */
    private function __construct(
        public AdmonitionKind $kind,
        private array $texts,
        private ?Node $after,
    ) {}

    /**
     * @throws Regex\Exception\ExceptionInterface When the fixed pattern cannot be applied, which it cannot
     * @throws Type\Exception\ExceptionInterface When a match is not the shape the pattern promises
     */
    public static function in(Paragraph $paragraph): ?self
    {
        $texts = [];
        $after = $paragraph->firstChild();

        while ($after instanceof Text) {
            $texts[] = $after;
            $after   = $after->next();
        }

        if ($after instanceof AbstractInline && ! $after instanceof Newline) {
            return null;
        }

        $captured = Regex\first_match(
            subject      : implode('', array_map(static fn(Text $text): string => $text->getLiteral(), $texts)),
            pattern      : self::PATTERN,
            captureGroups: Type\shape([1 => Type\string()]),
        );

        $kind = null === $captured ? null : AdmonitionKind::tryFrom(strtolower($captured[1]));

        return null === $kind ? null : new self($kind, $texts, $after);
    }

    /**
     * Take the marker line out of its paragraph, and the paragraph out of its parent if that was all it held.
     */
    public function remove(Paragraph $paragraph): void
    {
        foreach ($this->texts as $text) {
            $text->detach();
        }

        if ($this->after instanceof Newline) {
            $this->after->detach();
        }

        if (null === $paragraph->firstChild()) {
            $paragraph->detach();
        }
    }
}
