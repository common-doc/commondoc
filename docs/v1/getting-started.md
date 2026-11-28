# Getting started

Two setups. A site author needs the first, a commondoc contributor the second.

## Run a build locally

Requires a container runtime only. The image is the one the GitHub action runs, so a local
build matches the deployed one, and it pulls anonymously:

```bash
docker run --rm \
  -v "$PWD:/github/workspace" \
  ghcr.io/common-doc/commondoc:1 --help
```

Three tags can name one release: `1.0.0` is exact, `1` follows the newest 1.x, and `latest`
follows the newest release of any major. `1` and `latest` are published from a stable tag only,
so a prerelease publishes its own version tag and moves neither. A site that has to stay put
pins the exact one.

## Set up a site

A site repository holds its configuration, the templates it renders through, and the theme
assets it serves:

```text
<site repo>/
  commondoc.config.php            site configuration
  templates/site/document.phtml   body template, never published
  templates/site/layout.phtml     shell template, never published
  theme/site/css/site.css         theme asset, published at /theme/site/css/site.css
```

The configuration is one file returning an array. The image copies it to
`/app/config/autoload/commondoc.global.php` before the command runs.

```php
<?php

declare(strict_types=1);

use CommonDoc\Output\PathType;
use CommonDoc\SiteConfig;

return [
    'view_helper_config' => [
        'base_path' => '/',
    ],
    'theme' => [
        'active' => 'site',
        'themes' => [
            'site' => [
                'commondoc::document' => '/github/workspace/templates/site/document.phtml',
                'commondoc::layout'   => '/github/workspace/templates/site/layout.phtml',
            ],
        ],
        'assets' => [
            'site' => [
                'site.css' => 'css/site.css',
            ],
        ],
    ],
    SiteConfig::class => [
        'pathType'    => PathType::Directory,
        'output'      => '/github/workspace/build',
        'roots'       => [
            ['path' => '/github/workspace/theme', 'mount' => '/theme/'],
            ['path' => '/github/workspace/components/core', 'mount' => '/core/'],
        ],
        'siteName' => 'My documentation',
    ],
];
```

| Key | Purpose |
| --- | --- |
| `view_helper_config.base_path` | prefix every page and asset is published under |
| `theme.active`, `theme.themes` | which theme is used, and the template each name resolves to |
| `theme.assets` | asset name to path; a relative path is served under `/theme/<theme>/` |
| `SiteConfig.roots` | the directories read, and the mount each publishes at |
| `SiteConfig.output` | where the site is written; cleared before every build |
| `SiteConfig.pathType` | `<page>/index.html` or `<page>.html` |

Every path is read inside the container, where `/github/workspace` is the mounted checkout.
`commondoc::document` receives `documentBody` and `page`; `commondoc::layout` receives
`content`. [Theme and templates](reference/theme.md) and [Site
configuration](reference/configuration.md) cover both blocks in full.

## Build and view the site

```bash
docker run --rm \
  -e COMMONDOC_CONFIG=/github/workspace/commondoc.config.php \
  -v "$PWD:/github/workspace" \
  ghcr.io/common-doc/commondoc:1 \
  --output /github/workspace/build
```

Exit `0` prints one `Wrote` line per file. Exit `1` prints one `Failed` line per failure with
its reason, and still writes the pages that rendered. Exit `2` is a refusal: the configuration,
the source or the output directory could not be used, and nothing was written.

Serve the output directory to read the result. Any static server works:

```bash
php -S 127.0.0.1:8080 -t build
```

Rebuilding is enough to refresh: the files are read from disk on each request.

## Common mistakes

| Symptom | Cause |
| --- | --- |
| The build writes this package's documentation | a root path is relative, so it resolved under `/app`; use `/github/workspace/...` |
| The build names a target that is not a readable directory | a `repository` entry's `target` does not exist in that repository, so nothing was guessed |
| Nothing builds, the console help is printed | no argument was passed; the image's default command is `--help` |
| `.phtml` files appear in the published site | a template sits inside a source root; every non-Markdown file under a root is published |
| The stylesheet 404s | the theme directory is not inside a root mounted at `/theme/` |
| Links are wrong in the browser | `base_path` does not match where the site is served |
| The action cannot pull its image on a self-hosted runner | the published image is `linux/amd64` only |

## Work on commondoc

Requires git, and either Docker or PHP 8.4 or newer with `ext-curl`, `ext-json`, `ext-mbstring`
and Composer.

```bash
git clone https://github.com/common-doc/commondoc.git
cd commondoc
composer install
```

Without host PHP, run everything through the container: `docker compose up -d`, then prefix
each command with `docker compose exec tooling`.

| Command | Runs |
| --- | --- |
| `mago fmt` | rewrites what it can format, so run it first |
| `mago lint` | the linter |
| `mago analyze` | the analyser |
| `mago guard` | the architecture rules |
| `composer test` | the unit suite |
| `composer test-integration` | the integration suite, which builds real corpora |
| `composer mutation-test` | Infection, both MSI gates at 95 |

All four gates and both suites have to be clean. On Windows use `bin\mago.bat` in place of
`mago`. Fix a finding at its source; a suppression carries a reason beside it.

## Build and preview the documentation you are reading

```powershell
php vendor/bin/webware commondoc:build
```

The default configuration reads `docs/v1` and publishes at `/`. That writes `build/`, which is
gitignored; `--output` writes elsewhere. On Windows name the interpreter, because the Composer
proxy for `vendor/bin/webware` is extensionless and Windows has no shebang handling.

The preview container serves the same directory and rebuilds it on a change:

```bash
docker compose up -d preview     # http://localhost:8080/
docker compose down
```

Bound to `127.0.0.1` only. `base_path` must be `/`. If `build/` is deleted rather than rebuilt
into, run `docker compose restart preview`.

## Compose several checkouts

Add a gitignored `config/autoload/*.local.php` instead of editing the tracked default:

```php
<?php

declare(strict_types=1);

use CommonDoc\SiteConfig;

return [
    SiteConfig::class => [
        'target' => ['docs/{major}.0', 'docs/v{major}'],
        'roots'  => [
            ['repository' => 'C:/checkouts/message-bus', 'versions' => true],
            ['repository' => 'C:/checkouts/webware-log', 'versions' => true],
        ],
    ],
];
```

`versions` publishes every released version at `/{component}/v{major}/`. `target` may be a
list, tried in order against each tag.
