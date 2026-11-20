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

namespace CommonDocTest;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use CommonDoc\Output\DocumentRoot;
use CommonDoc\SiteConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The site holds what is constant for one deployment.
 *
 * The invariant worth its own test is that mounts are exclusive. Two roots whose
 * paths overlap leave the owner of a path to iteration order, and the result is
 * a file from one repository published at a path belonging to another, which
 * nobody can diagnose from the site.
 */
#[CoversClass(SiteConfig::class)]
#[CoversMethod(SiteConfig::class, '__construct')]
final class SiteConfigTest extends TestCase
{
    #[Test]
    public function acceptsRootsThatPublishAtDistinctMounts(): void
    {
        $site = new SiteConfig(
            documentRoot: new DocumentRoot('/'),
            roots       : [
                new Root(
                    path : 'a',
                    mount: '/one/',
                ),
                new Root(
                    path : 'b',
                    mount: '/other/',
                ),
            ],
        );

        static::assertCount(2, $site->roots);
    }

    #[Test]
    public function holdsThePathSpaceTheRootsAndTheIdentity(): void
    {
        $documentRoot = new DocumentRoot('/repo/');
        $root         = new Root(path: 'docs');

        $site = new SiteConfig(
            documentRoot: $documentRoot,
            roots       : [$root],
            output      : 'site',
            projectName : 'CommonDoc',
            projectSlug : 'commondoc',
            versionLabel: '1.0',
        );

        static::assertSame($documentRoot, $site->documentRoot);
        static::assertSame([$root], $site->roots);
        static::assertSame('site', $site->output);
        static::assertSame('CommonDoc', $site->projectName);
        static::assertSame('commondoc', $site->projectSlug);
        static::assertSame('1.0', $site->versionLabel);
    }

    #[Test]
    public function refusesOneRootMountedBeneathAnother(): void
    {
        // Named so the operator can see which two, rather than being told a site
        // could not be built.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"a" and "b".*"\/" and "\/guide\/"/');

        new SiteConfig(
            documentRoot: new DocumentRoot('/'),
            roots       : [
                new Root(path: 'a'),
                new Root(
                    path : 'b',
                    mount: '/guide/',
                ),
            ],
        );
    }

    #[Test]
    public function refusesTwoRootsAtOneMount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SiteConfig(
            documentRoot: new DocumentRoot('/'),
            roots       : [
                new Root(
                    path : 'a',
                    mount: '/guide/',
                ),
                new Root(
                    path : 'b',
                    mount: '/guide/',
                ),
            ],
        );
    }
}
