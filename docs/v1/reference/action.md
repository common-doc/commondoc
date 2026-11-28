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

## Several repositories, listed one by one

Not every site can use an organization search. The repositories may be private, the
organization may not carry the custom property, or no token may be available. Listing each
repository as a root works in all three cases, and the workflow is what puts them on disk.

```yaml
- name: Check out the site definition
  uses: actions/checkout@v7

- name: Check out the repositories the site is composed from
  uses: actions/checkout@v7
  with:
    repository: acme/one
    path: components/one

- uses: actions/checkout@v7
  with:
    repository: acme/two
    path: components/two
```

Each root then names its checkout, absolutely, and the mount it publishes at:

```php
'roots' => [
    ['path' => '/github/workspace/components/one', 'mount' => '/one/'],
    ['path' => '/github/workspace/components/two', 'mount' => '/two/'],
],
```

Four rules apply to a list like that.

- **One mount per root, and they must not overlap.** A root at `/` publishes everything
  beneath it, so no other root can be mounted inside that space. The site's own pages at
  `/` and the components beside it means the site root is one root and each component is
  another. [Site configuration](configuration.md#mount) states the refusal.
- **Every root's target has to exist in the repository it names.** A `repository` entry
  whose `target` matches nothing is refused by name rather than skipped, so a repository
  that will be documented later cannot be listed early. Leave it out until its
  documentation directory exists. [Site configuration](configuration.md#target) covers the
  candidates.
- **A checkout carries the workflow's token, so private repositories work.** This is the
  difference between the two composition styles: the organization search clones with no
  credential and therefore sees public repositories only, while a checkout step uses the
  token the workflow already has.
- **A repository with no document is published but not listed.** Its files are copied, and
  it gets no card on the home page and no entry in the components menu, because there is no
  page to link to. A directory of theme files mounted at `/theme/` is the usual case.

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

## Rebuilding on a release tag

A versioned site reads its tags at build time, so nothing in the site definition changes
when a release is cut. What has to happen is the build, and a tag in a documented
repository does not reach the site's workflow: they are different repositories, and a
workflow only starts from an event in the repository that holds it.

Two things close that, and a site may use either or both:

- `repository_dispatch`, sent by the repository that cut the tag. The site rebuilds within
  a minute of the release.
- `schedule`, on the site itself. Nothing has to be asked to send it, so it also serves
  repositories that carry no workflow at all. It costs latency up to the interval, and
  GitHub disables a scheduled workflow in a public repository after 60 days without a
  commit, so it needs the schedule line kept alive by ordinary work.

A manual dispatch remains available for a build nobody's release triggered.

## The dispatch token

Sending a dispatch is a write against the site's repository, so it needs a token that
may write there and nothing more:

1. Create a fine-grained personal access token owned by the organization that owns the
   site, with repository access limited to the site repository, and one permission:
   **Contents: Read and write**. That is what `repository_dispatch` requires; every other
   permission stays at no access.
2. Store it as an organization secret granted to the repositories that send the dispatch,
   not to the site repository:

   ```bash
   gh secret set DOCS_DISPATCH_TOKEN --org <owner> --repos <component>,<component>
   ```

   An organization secret is inherited by the repositories you grant it to, so one token
   serves every component that releases, and a component added later is granted access
   rather than given a copy.

The search token described under [organizations](#building-every-repository-of-an-organization)
is a different credential with different permissions, because it reads repositories rather
than writing to one.

## Wiring the dispatch

The dispatch only starts a workflow that exists on the site's default branch, so the
`on` block of the site's workflow gains the event:

```yaml
on:
  push:
    branches: [main]
  repository_dispatch:
  workflow_dispatch:
```

The releasing repository asks for the build from a workflow that runs on its tags, and
from nothing else:

```yaml
on:
  push:
    tags: ['[0-9]*']

# ...

      - name: Ask the documentation site to rebuild
        env:
          GH_TOKEN: ${{ secrets.DOCS_DISPATCH_TOKEN }}
        run: gh api "repos/<owner>/<site>/dispatches" -f event_type=docs
```

A repository that publishes several things keeps this apart from each of them. The step
belongs to the release, not to a build that happens to run on the same tag, so a component
that publishes no image still has the one file to copy.

The event carries no payload. The build reads every composed repository's tags when it
runs, so a tag, a branch, or a version number in the payload would be a second answer to a
question the build already answers.

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

The image is public, so pulling it needs no credentials, and three tags can name one
release: `1.0.0` is exact, `1` follows the newest 1.x, and `latest` follows the newest
release of any major. `1` and `latest` are published from a stable tag only, so a
prerelease publishes its own version tag and moves neither. A site that has to stay put
pins the exact tag.

The image is `linux/amd64` only. A GitHub-hosted `ubuntu-latest` runner is
amd64, so the action runs there unchanged; a self-hosted runner on another
architecture needs an image built for it.

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
