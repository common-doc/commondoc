# GitHub action

The package publishes a runtime image and a container action that runs it, so a
workflow can build a site without a PHP toolchain on the runner.

```yaml
- uses: common-doc/commondoc@1.0.0
  with:
    output: /github/workspace/build
```

## Inputs

| Name | Required | Meaning |
| --- | --- | --- |
| `source` | no | Absolute path to one directory to build alone, published at `/`. Leave it empty to build the site the configuration describes. |
| `output` | no | Absolute path of the directory that receives the rendered site. Defaults to `/github/workspace/build`. |
| `github-token` | no | Token used to search an organization for the repositories that opt in. Defaults to the workflow's own token. |

Both paths are absolute for a reason. The console entry point belongs to
`webware-console`, and it resolves the project root from the position of the
Composer autoloader. Inside the image the autoloader is at `/app/vendor`, so
`/app` is the project root and the process changes directory there before it
does anything else. A relative path would therefore mean something under `/app`
rather than something under your checkout.

An empty `source` is ignored by the command, which is what keeps the
configuration the site definition: the action without `source` builds the roots
the configuration names, and with `source` it builds one directory at `/`.

## A composed site

`commondoc:build` has no `--config` option, because configuration is aggregated
before any command runs. A composed site therefore supplies its own file through
the environment, and the image installs it before the console starts:

```yaml
- name: Check out every repository the site is composed from
  uses: actions/checkout@v7
  with:
    repository: common-doc/commondoc
    path: site/commondoc
- name: Check out a second repository
  uses: actions/checkout@v7
  with:
    repository: common-doc/aggregate-renderer
    path: site/aggregate-renderer
- uses: common-doc/commondoc@1.0.0
  env:
    COMMONDOC_CONFIG: /github/workspace/site/commondoc.config.php
  with:
    source: ''
    output: /github/workspace/build
```

The file is installed as `/app/config/autoload/commondoc.global.php` and must be
a complete `commondoc.global.php`, so the root list, the target, the mount
points, the base path and the output directory all live in it. The
[site configuration](configuration.md) reference covers every key. Paths inside
it are resolved the same way command line paths are, so they are absolute:
`/github/workspace/site/commondoc` rather than `site/commondoc`.

A `COMMONDOC_CONFIG` that is set but unreadable is refused and the build exits
`2`. A workflow that named a file and quietly got a build of the image's own
documentation instead would look like a successful run of the wrong site.

## Building every repository of an organization

A `roots` entry can name an organization instead of a repository, and the build
then finds every repository of it that opts in. Opting in is a custom property:
a repository whose `doc-target` property is `true` is built, and nothing else is
listed in the configuration beyond the organization's name.

```php
<?php

declare(strict_types=1);

use CommonDoc\Output\PathType;
use CommonDoc\SiteConfig;

return [
    'view_helper_config' => [
        'base_path' => '/',
    ],
    SiteConfig::class => [
        'pathType' => PathType::Directory,
        'output'   => '/github/workspace/build',
        'target'   => ['docs/{major}.0', 'docs/v{major}'],
        'roots'    => [
            [
                'organization' => 'webinertia',
                'exclude'      => ['docs.web-ware.net'],
                'target'       => ['docs/{major}.0', 'docs/v{major}'],
            ],
        ],
    ],
];
```

No checkout step is needed for those repositories. The build clones each one
itself, with its tags, and reads every released version from the tags, so a step
that checked them out as well would only duplicate the work. Archived
repositories are left out, and `exclude` names the ones to leave out by hand.

The search needs a token that can read the organization's repositories and their
custom properties, and that token is `github-token`:

```yaml
- uses: common-doc/commondoc@1
  env:
    COMMONDOC_CONFIG: /github/workspace/commondoc.config.php
  with:
    github-token: ${{ secrets.DOCS_TOKEN }}
    output: /github/workspace/build
```

The token is used for the search. Each clone is a plain `git clone` of the
repository's clone URL, and no credential is handed to git, so the action builds
public repositories; a private repository needs the clone itself to be given a
token, which is not something this package does yet.

## Publishing to GitHub Pages

Pages takes a directory of files, so the build writes the site into the workspace
and `upload-pages-artifact` uploads it. A repository that publishes its own
documentation keeps the site definition beside the workflow, and
`.github/workflows/commondoc.yml` is the whole of it:

```yaml
name: commondoc

on:
  push:
    branches: [1.0.x]
  workflow_dispatch:

permissions:
  contents: read
  pages: write
  id-token: write

concurrency:
  group: pages
  cancel-in-progress: false

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v7
      - uses: common-doc/commondoc@1
        env:
          COMMONDOC_CONFIG: /github/workspace/commondoc.config.php
        with:
          output: /github/workspace/build
      - uses: actions/upload-pages-artifact@v5
        with:
          path: build

  deploy:
    needs: build
    runs-on: ubuntu-latest
    environment:
      name: github-pages
      url: ${{ steps.deployment.outputs.page_url }}
    steps:
      - id: deployment
        uses: actions/deploy-pages@v5
```

The two paths are written differently for a reason. `output` is absolute because
the container is a separate filesystem and the console changes directory to
`/app`; `path: build` is relative because `upload-pages-artifact` runs on the
runner, in the workspace, after the container has exited.

The repository's Pages source has to be set to `GitHub Actions` once, under
Settings then Pages. The first run fails the deploy step until it is, because
there is no Pages site to deploy to.

## Base path

`view_helper_config.base_path` is where the site is served, and both the paths a
build writes and the links a template emits are made from it. A custom domain and
an organization site publish at `/`; a project site publishes at its repository
name, so a repository building its own pages states it as:

```php
'view_helper_config' => [
    'base_path' => '/commondoc/',
],
```

A wrong value produces a site that looks right on disk and resolves nothing once
served: every file is where the build put it and every link points one segment
off. The site section does not repeat the value, because a second statement of it
is a second thing to get wrong.

## The image

`ghcr.io/common-doc/commondoc` is built from `docker/Dockerfile` by the
`Publish image` workflow, which runs on a release tag and publishes the version,
the `1` major alias the action references, and `latest`. The image carries PHP,
the extensions the package requires, git, the runtime dependencies, and the
console entry point. It does not carry Mago, Xdebug, PCOV, PHPUnit, Infection or
the dev dependencies.

Nothing is pushed until the image has passed its assertions: that it carries no
dev toolchain, that it offers `commondoc:build`, that a build renders a document
and gives its headings a linkable `id`, that git is present and reads a
repository owned by another user, that the PHP extensions load, and that a
versioned build reads a git tag and writes that version's pages. The image that
passes them is the image that is pushed, because it is tagged from the same local
build rather than built again.

The image is public, so pulling it needs no credentials. That is a package
setting rather than something the workflow can set: after the first push, set the
package's visibility to Public in the package settings, because until it is set a
workflow pulling `ghcr.io/common-doc/commondoc:1` fails with a missing manifest.

The action passes no token to the container unless the step gives it one:
`github-token` is the only credential it can hand over, and it is used for an
organization search.

## Running the image without the action

The image is a normal container, and the entrypoint takes the command's own
arguments. With nothing to pass it builds the site the configuration describes,
which is what the command itself does when it has nothing to override:

```bash
docker run --rm -v "$PWD:/workspace" ghcr.io/common-doc/commondoc:1 /workspace --output /workspace/build
docker run --rm -v "$PWD:/workspace" ghcr.io/common-doc/commondoc:1 --output /workspace/build
docker run --rm ghcr.io/common-doc/commondoc:1 --help
```

An argument list that begins with one of the console's own options or commands
(`--version`, `--help`, `list`, `help`) goes to the console as it is, which is
what makes `--help` and `--version` print the console's own output. Any other
argument list, including `--output` alone and no arguments at all, is passed to
`commondoc:build`.

The image carries this package's own documentation, so a run with no
configuration and no arguments builds a real site rather than failing. That tree
is also what the publish workflow builds as its smoke test.

## Related

- [Command reference](cli.md) lists the arguments and exit codes
- [Site configuration](configuration.md) covers composition, mounts and targets
