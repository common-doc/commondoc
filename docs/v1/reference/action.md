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

## The image

`ghcr.io/common-doc/commondoc` is built from `docker/Dockerfile` by the
`Publish image` workflow, which runs on a release tag and publishes the version,
the `1` major alias the action references, and `latest`. The image carries PHP,
the three extensions the package requires, the runtime dependencies, and the
console entry point. It does not carry Mago, Xdebug, PCOV, PHPUnit, Infection or
the dev dependencies; the workflow asserts that before it pushes, and asserts
that a build renders a document and gives its headings a linkable `id`.

The image is public, so pulling it needs no credentials. Your workflow's own
`GITHUB_TOKEN` is never passed to the container by the action: the only
credential it can see is one your step puts into its environment.

## Running the image without the action

The image is a normal container, and the entrypoint takes the command's own
arguments. With nothing to pass it builds the site the configuration describes,
which is what the command itself does when it has nothing to override:

```
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
