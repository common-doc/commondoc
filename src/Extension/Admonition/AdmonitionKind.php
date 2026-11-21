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

/**
 * The five kinds of admonition, named as GitHub's alert syntax names them.
 *
 * A kind says how it is written (`[!NOTE]`), what it is called on the page, which Bootstrap
 * alert colour it takes and which icon it carries, so the markup and the theme's styles are
 * described in one place. `important` has no alert variant of its own: the theme supplies its
 * purple through `admonition-important`.
 *
 * @api
 */
enum AdmonitionKind: string
{
    case Note      = 'note';
    case Tip       = 'tip';
    case Important = 'important';
    case Warning   = 'warning';
    case Caution   = 'caution';

    /**
     * The Bootstrap alert variant class, or an empty string for the kind the theme colours itself.
     */
    public function alert(): string
    {
        return match ($this) {
            self::Note      => 'alert-info',
            self::Tip       => 'alert-success',
            self::Important => '',
            self::Warning   => 'alert-warning',
            self::Caution   => 'alert-danger',
        };
    }

    /**
     * The Bootstrap Icons class the title carries.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Note      => 'bi-info-circle-fill',
            self::Tip       => 'bi-lightbulb-fill',
            self::Important => 'bi-exclamation-diamond-fill',
            self::Warning   => 'bi-exclamation-triangle-fill',
            self::Caution   => 'bi-x-octagon-fill',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Note      => 'Note',
            self::Tip       => 'Tip',
            self::Important => 'Important',
            self::Warning   => 'Warning',
            self::Caution   => 'Caution',
        };
    }
}
