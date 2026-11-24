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

use Laminas\Filter\Callback;
use Laminas\Filter\Exception\ExceptionInterface as FilterException;
use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterPluginManager;
use Laminas\Filter\PregReplace;
use Laminas\Filter\StringTrim;
use Laminas\ServiceManager\ServiceManager;
use Psl\Str;
use Psr\Container\ContainerExceptionInterface;

use function is_string;

/**
 * The one rule that turns a file name or a path segment into a label.
 *
 * A label is shown in the page menu and in the page title, so it has to read as English
 * rather than as a path: separators become spaces and each word is capitalised, which turns
 * `api-reference` into `Api Reference`.
 *
 * A laminas filter chain rather than a hand-written loop, because a second normalisation that
 * was written by hand is how two answers to the same question start to disagree. Every place
 * that produces a label calls this, and a change to the rule reaches all of them.
 *
 * The plugin manager is a lookup table for the three filters below; none of them reads the
 * container, which is why a bare one is enough.
 *
 * A failure falls back to the value as written rather than escaping. The chain is fixed, so a
 * failure means this package is broken, and failing a whole build over the wording of a menu
 * entry would be the wrong trade: the page still publishes and the label still says something
 * true.
 *
 * @internal
 */
final class Label
{
    /** Runs of any separator people write, including a name that already has spaces. */
    private const string SEPARATORS = '~[-_\s]+~';

    /**
     * @param string $value A file name without its suffix, or a path segment
     */
    public static function of(string $value): string
    {
        try {
            /** @var mixed $labeled */
            $labeled = self::chain()->filter($value);
        } catch (ContainerExceptionInterface|FilterException) {
            return $value;
        }

        return is_string($labeled) && '' !== $labeled ? $labeled : $value;
    }

    /**
     * @throws ContainerExceptionInterface When the plugin manager refuses a filter, which it
     *         cannot for the three named here
     * @throws FilterException When a filter cannot be built from its options
     */
    private static function chain(): FilterChain
    {
        return new FilterChain(
            plugins: new FilterPluginManager(new ServiceManager()),
            options: [
                'filters' => [
                    [
                        'name'    => StringTrim::class,
                        'options' => [],
                    ],
                    [
                        'name'    => PregReplace::class,
                        'options' => [
                            'pattern'     => self::SEPARATORS,
                            'replacement' => ' ',
                        ],
                    ],
                    [
                        'name'    => Callback::class,
                        'options' => [
                            'callback' => Str\capitalize_words(...),
                        ],
                    ],
                ],
            ],
        );
    }
}
