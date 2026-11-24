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

namespace CommonDoc\Input;

use CommonDoc\Exception\InvalidArgumentException;

use function preg_replace;
use function sprintf;
use function str_ends_with;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

/**
 * The identity a repository name carries on the published site.
 *
 * One input, two outputs, and they are produced together on purpose. A component
 * is addressed by a path segment (`/message-bus/`) and named in a menu by a label
 * (`Message Bus`), and both are normalized forms of the same repository name. If
 * either were stated separately, a site could declare a label whose segment says
 * something else, and nothing in a build would ever detect the disagreement: the
 * two facts never meet.
 *
 * Deriving both here removes the class of failure rather than policing it. There
 * is no second value for an operator to type, so there is nothing that can drift.
 *
 * The two normalizations differ because they answer different questions. The
 * segment has to survive a URL, so it is lowercase with runs of punctuation
 * collapsed to hyphens. The label has to read as English, so it keeps word
 * boundaries and title-cases each word.
 *
 * An owner prefix is stripped, because a component is a repository rather than an
 * account: `webinertia/message-bus` and `message-bus` describe the same component.
 *
 * @api
 */
final readonly class Component
{
    private const string SEPARATOR = '-';

    /** Anything that ends one word and starts another, in any name people write. */
    private const string WORD_BREAK = '~[^a-z0-9]+~';

    private const string SUFFIX = '.git';

    /**
     * The path segment this component is published under, such as `message-bus`.
     *
     * @var non-empty-string
     */
    public string $segment;

    /**
     * The name this component carries in a menu, such as `Message Bus`.
     *
     * @var non-empty-string
     */
    public string $label;

    /**
     * @param string $name The repository name, with or without an owner, and with or
     *                     without a `.git` suffix
     *
     * @throws InvalidArgumentException When the name carries no word to normalize
     */
    public function __construct(string $name)
    {
        $this->segment = self::segmentOf(self::repositoryOf($name));
        $this->label   = self::labelOf($this->segment);
    }

    /**
     * The display form, with separators removed and each word title-cased.
     *
     * Derived from the segment rather than from the original name, so the label
     * and the address cannot be normalized by two rules that disagree: both come
     * from one traversal.
     *
     * @param non-empty-string $segment
     *
     * @return non-empty-string
     *
     */
    private static function labelOf(string $segment): string
    {
        // The chain falls back to what it was given, and a segment is never empty.
        /** @var non-empty-string */
        return Label::of($segment);
    }

    /**
     * The repository name without its owner or its suffix.
     *
     * Only the last segment is kept, because that is the part a repository is
     * commonly called by and the part a reader recognizes in a menu.
     */
    private static function repositoryOf(string $name): string
    {
        $trimmed = trim($name);

        if (str_ends_with($trimmed, self::SUFFIX)) {
            $trimmed = substr(
                string: $trimmed,
                offset: 0,
                length: -4,
            );
        }

        $owner = strrpos($trimmed, needle: '/');

        return false === $owner ? $trimmed : substr($trimmed, offset: $owner + 1);
    }

    /**
     * The lowercase, hyphenated form a URL segment can carry.
     *
     * Runs rather than single characters, so `message_bus`, `Message Bus` and
     * `message--bus` all answer `message-bus`: an operator's spelling should not
     * reach the published address.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When nothing is left to address
     */
    private static function segmentOf(string $repository): string
    {
        $segment = preg_replace(
            pattern    : self::WORD_BREAK,
            replacement: self::SEPARATOR,
            subject    : strtolower($repository),
        );

        $segment = null === $segment ? '' : trim($segment, characters: self::SEPARATOR);

        if ('' === $segment) {
            throw new InvalidArgumentException(sprintf(
                'The component name "%s" carries no word that can address a path, so it cannot be published.',
                $repository,
            ));
        }

        return $segment;
    }
}
