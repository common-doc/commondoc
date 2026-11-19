# Layer findings: implementation detail

**Date**: 2026-11-19
**Component**: `common-doc/commondoc`
**Branch**: `tyrsson-orchestration` at `0049a4c`

Fine-grained observations captured while walking the layers. They are recorded
here so the architectural discussion stays at the architectural level. Nothing
here is a decision, and nothing here authorises code.

Companion to `2026-11-18-layer-walkthrough.md`, which holds decisions only.

---

## Layer 0: app entry

- `bin/commondoc` was byte-identical to `vendor/webware/webware-console/bin/webware`,
  differing at one comment punctuation mark. Deleted in `0049a4c`.
- The Composer proxy sets `$GLOBALS['_composer_autoload_path']` and includes the
  upstream file, so the copy was never reached.
- `bin/commondoc` probed three autoloader locations; only the first is exercised
  when installed normally.
- `--version` reports `webware-console 0.1.x`, from the vendor factory rather
  than from this package.
- `config/config.php` passes `data/cache/config-cache.php` as the cache path, and
  `Webware\ConfigManager\ConfigAggregator` redeclares `ENABLE_CACHE` and
  `CACHE_FILEMODE` for drop-in compatibility. The container's config can
  therefore be a cached file rather than the aggregated arrays.

## Layer 1: console surface

### `execute()` status contract

- `MenuCommand::execute()` calls `CommandRunner::run()`, which calls
  `Command::run()` directly. Neither has a `try`/`catch`; verified by grep.
- `Application::run()` is the only renderer of thrown exceptions, so a throw
  inside a command reached from the menu bypasses all rendering.
- `MenuCommand::formatResult()` treats `0 === $status` as success and renders
  anything else red as `Status: command failed (N)`.
- Symfony constants: `SUCCESS = 0`, `FAILURE = 1`, `INVALID = 2`.

### Input guards

- `InputArgument::REQUIRED` with a non-array argument yields `string`.
- `InputOption::VALUE_REQUIRED` yields `string`.
- The `is_string()` tests on both are therefore unreachable.
- The `/** @var mixed */` annotations on both exist only to satisfy the analyzer.

### Naming

- `foreach ($raw->output as $document)` was accurate while the record held only
  documents. Since `7defbe1` it holds documents and assets, so an asset failure
  reads `Failed site.css: ...` under a variable named `$document`.
- `BuildRecord::$output` and `$failed` hold either kind; `$input` and `$asset`
  distinguish them.

## Windows invocation

### Confirmed working

```powershell
php vendor/bin/webware commondoc:build "C:\path\to\docs" --output "C:\path\to\out"
```

Exit `0`, and the expected document, nested document and asset were written. A
Windows absolute path with backslashes is accepted for both arguments.

Also exit `0`: `vendor\bin\webware.bat` through `cmd`, and the extensionless
`vendor\bin\webware` proxy. Neither is worth relying on. `php` as an explicit
prefix is the form that does not depend on shell association.

### Why the `php` prefix

`vendor/bin/webware` is Composer's generated proxy: an extensionless file whose
first line is a `#!/usr/bin/env php` shebang. Windows has no shebang handling, so
the interpreter has to be named.

### The trap: the binary changes directory

`vendor/webware/webware-console/bin/webware` ends with `chdir(dirname($autoload,
2))`, which is the consuming project's root. Every relative path in a command is
therefore resolved against **the project root, not the caller's working
directory**.

Measured from `%TEMP%` with `commondoc:build src --output outrel`: both arguments
resolved against the repository root, so it walked the package's own `src/`
directory and wrote it to `<repo>/outrel/`. Exit `0`, no failures, and the `.php`
files were copied as assets because only `.md` is a document. Removed afterwards.

Consequence: pass absolute paths, or accept that relative paths are
project-relative.

## The dev corpus

`docs/v1/` is a source tree that builds and is also the project's own
documentation. It exists so a build can be driven hands on:

```powershell
php vendor/bin/webware commondoc:build "$PWD\docs\v1" --output "$PWD\build"
```

Measured: eight paths written, exit `0`.

```
index.html
getting-started/index.html
concepts/index.html
concepts/document/index.html
concepts/address/index.html
reference/cli/index.html
assets/site.css
assets/flow.svg
```

It covers every pass. Nested `index.md` and a nested leaf document exercise the
index rule and both file layouts. A relative link across directories, an absolute
link from the root and a suffix-bearing link all appear. Two assets, a stylesheet
and an image, are referenced from documents, so the asset pass and the image
branch of the rewriter are both reached. Tables, task lists, the heading permalink
and the table of contents render because the always-on extensions are exercised by
real content rather than by a fixture.

It doubles as a link checker. Editing a link to name a file that does not exist
produces a recorded failure naming both sides and exit `1`, verified by mutating
a copy of the tree.

### Observations from the first build

- `concepts/index.md` renders `<title>index</title>`. The label is derived from
  the file name with the suffix stripped, so every index page is labelled
  `index`. Visible on any site whose navigation reads the label.
- The table of contents includes the H1, because `min_heading_level` is 1. That
  is config, not code.
- The output directory is not gitignored. A build into `build/` leaves the tree
  dirty.

## GitHub Pages

### Measured state

- `gh api repos/common-doc/commondoc` reports `has_pages: false`, so Pages has
  never been enabled for this repository.
- Default branch is `1.0.x`, not `main`.
- `.github/workflows` does not exist.
- The `Dockerfile` in the repository builds a development tooling image
  (`FROM php:8.5-cli`, Mago, Xdebug, `command: sleep infinity`). It is not a
  publisher and cannot become one by adding a build step, because its purpose is
  an interactive shell.

### `gh-pages` is a convention, not a requirement

Pages accepts two sources:

| Source | What is deployed | Branch needed |
| --- | --- | --- |
| Deploy from a branch | the chosen branch, folder `/` or `/docs` | yes, any name |
| GitHub Actions | an uploaded artifact | no |

A branch called `gh-pages` is what the first option grew up with. Nothing
requires the name, and the second option has no branch at all.

### Two traps specific to this repository

**The `/docs` folder option would publish the sources.** The Pages UI offers
`/docs` as a folder on the branch. This repository's Markdown sources are at
`docs/v1`, so choosing it would serve the raw inputs. The `docs/` directory is
required by the corpus and is not available as a publication target.

**Jekyll drops underscore paths on branch deployments.** Sites deployed from a
branch pass through Jekyll unless a `.nojekyll` file is present. Jekyll ignores
any file or directory beginning with `_`. Artifact deployments do not run Jekyll,
so the exposure is specific to the branch route and is another reason the
artifact route is the one to use.

### Base path

A project site is served from `https://<owner>.github.io/<repo>/`, so
`DocumentRoot::basePath` must be `/commondoc/`. Today
`config/autoload/commondoc.global.php` holds `'basePath' => '/'`, which is correct
for an organization site or a custom domain and wrong for this one.

Directory output (`PathType::Directory`) produces `guide/setup/index.html`, which
Pages serves at `/commondoc/guide/setup/`. That matches the address the rewriter
emits, so the two agree without any Pages-specific handling.

### Still missing

- `.gitignore` does not list `/build/`, so a local build dirties the tree.
- No workflow exists, so nothing deploys.

## Cross-document heading fragments do not resolve

Measured while writing the end-to-end corpus test.

A source link written `address.md#the-index-rule` is rewritten to
`/concepts/address/#the-index-rule`. The rewriter splits the target at `#` or `?`,
translates only the path, and appends the suffix unchanged. That part is correct:
the fragment belongs to the target document.

The fragment does not match the id that is actually emitted, though.
`commonmark.global.php` sets:

```
'heading_permalink' => [
    'id_prefix'           => 'content',
    'apply_id_to_heading' => false,
],
```

With `apply_id_to_heading` false, the id lands on the permalink anchor, not on the
heading, and it carries the `content` prefix. The document contains
`<a id="content-the-index-rule" ...>` and nothing with `id="the-index-rule"`, so an
authored `#the-index-rule` reaches a page with no such anchor.

Two ways out, and the choice is a config decision rather than a code one:

| Change | Effect |
| --- | --- |
| `apply_id_to_heading` true, `id_prefix` empty | authored fragments match, permalink anchors still present |
| rewrite fragments through the prefix | the rewriter learns a rendering option, which is coupling in the wrong direction |

The first keeps the rewriter ignorant of rendering, which is the property that
makes it testable with a bare `Document`.

The corpus test asserts the current output
(`href="/concepts/address/#the-index-rule"`) so the behaviour is pinned rather than
assumed, and the assertion is annotated to point here.
