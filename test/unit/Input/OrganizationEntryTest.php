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

namespace CommonDocTest\Input;

use CommonDoc\Input\OrganizationEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationEntry::class)]
#[CoversMethod(OrganizationEntry::class, 'from')]
final class OrganizationEntryTest extends TestCase
{
    #[Test]
    public function fallsBackToTheDefaultPropertyWhenTheOneStatedIsEmpty(): void
    {
        static::assertSame('doc-target', OrganizationEntry::from(['organization' => 'o', 'property' => ''])?->property);
    }

    #[Test]
    public function isNotAnEntryWithoutAnOrganization(): void
    {
        static::assertNull(OrganizationEntry::from('webinertia'));
        static::assertNull(OrganizationEntry::from(['repository' => 'x']));
        static::assertNull(OrganizationEntry::from(['organization' => '']));
        static::assertNull(OrganizationEntry::from(['organization' => 5]));
    }

    #[Test]
    public function readsAStatedPropertyTheExclusionsAndTheTarget(): void
    {
        $entry = OrganizationEntry::from([
            'organization' => 'o',
            'property'     => 'publish',
            'exclude'      => ['site', 7, 'other'],
            'target'       => ['docs/{major}.0'],
        ]);

        static::assertNotNull($entry);
        static::assertSame('publish', $entry->property);
        static::assertSame(['site', 'other'], $entry->exclude);
        static::assertSame(['docs/{major}.0'], $entry->target);
    }

    #[Test]
    public function readsTheOrganizationAndDefaultsTheProperty(): void
    {
        $entry = OrganizationEntry::from(['organization' => 'webinertia']);

        static::assertNotNull($entry);
        static::assertSame('webinertia', $entry->organization);
        static::assertSame('doc-target', $entry->property);
        static::assertSame([], $entry->exclude);
        static::assertNull($entry->target);
    }
}
