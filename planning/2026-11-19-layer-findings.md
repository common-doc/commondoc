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

## Cross-document heading fragments did not resolve

Measured while writing the end-to-end corpus test, and resolved on 2026-11-19.

A source link written `address.md#the-index-rule` is rewritten to
`/concepts/address/#the-index-rule`. The rewriter splits the target at `#` or `?`,
translates only the path, and appends the suffix unchanged. That part is correct:
the fragment belongs to the target document.

The fragment did not match the id that was emitted. `commonmark.global.php` set:

```
'heading_permalink' => [
    'id_prefix'           => 'content',
    'apply_id_to_heading' => false,
],
```

With `apply_id_to_heading` false the id landed on the permalink anchor rather than
on the heading, and it carried the `content` prefix. The document contained
`<a id="content-the-index-rule" ...>` and nothing with `id="the-index-rule"`, so an
authored `#the-index-rule` reached a page with no such anchor.

### Resolution

**D39. A heading carries its own slug as its id, with no prefix.** `id_prefix` and
`fragment_prefix` are both empty and `apply_id_to_heading` is true:

```
<h2 id="the-index-rule"><a href="#the-index-rule" class="heading-permalink" ...>¶</a>The index rule</h2>
```

The deciding argument is not tidiness, it is that a section link must be constructible
without knowing this package's configuration. Section linking belongs to
`webware-navigation`, and a consumer building `#slug` from a heading cannot do that if
the anchor is `content-slug`. A prefix makes every link builder learn a private
convention; no prefix makes the id derivable from the heading it came from.

The earlier option of teaching `LinkRewriter` the prefix is now wrong for the same
reason in the other direction: it would put rendering knowledge inside the rewriter,
which is the coupling the rewriter exists without.

Verified in a real build: the target page has `<h2 id="the-index-rule">`, the authored
link is unchanged at `/concepts/address/#the-index-rule`, the permalinks still render,
the table of contents points at the same plain slugs, and no `content-` id remains
anywhere in the output. The corpus test asserts the id rather than only the href.

## The heading inventory

An id a consumer can construct from a heading is only half of section linking. The other
half is finding the headings: whoever writes a `fragment` into a navigation options array
has to name a section, and until now the only way to learn a document's sections was to
build the site and read the HTML.

### Resolution

**D40. A document's headings are collected as link targets, read from the id the renderer
emits.** `View\HeadingListener` runs on the parsed event at priority `-200` and puts a
`View\Heading` (level, label, fragment) into `PageContext::sections()` for each heading.

Three things about it are deliberate rather than incidental:

- **The fragment is read from `attributes/id`, not derived from the text.** That is the
  same value the renderer writes to the heading's `id` attribute, so a fragment cannot
  drift from the anchor it has to reach. Deriving the slug a second time would be a second
  implementation of a rule that belongs to the permalink extension.
- **The label comes from `StringContainerHelper` with raw markup excluded**, which is the
  reduction the permalink extension itself applies. Label and slug then agree: a heading
  whose slug names `set()` is not labelled without it, and a heading containing
  `<span>` is not labelled with the tag.
- **A heading with no id is skipped, not refused.** Ids come from an extension, and a
  deployment without the permalink extension still builds a valid site; it simply has no
  sections to link to. Refusing would turn a rendering choice into a failed build.

The listeners are ordered explicitly rather than by addition: this one must run after the
permalink extension, which runs at `-100`, and a listener at equal priority would be
decided by registration order instead (`#38`).

The inventory is not handed to the layout. Nothing renders a per-page table of contents
today, and the consumer that needs fragments is the navigation side, which builds its
options outside this package. Adding a template variable for a reader that does not exist
would be a second unfinished edge, so the read is `PageContext::sections()` and the
consumer is yet to be written.

### Two gaps mutation testing found in the tests, not the code

Both escapes were in `HeadingListenerTest` being too kind to the code:

- `UnwrapTrim` survived, so nothing proved the label is trimmed. A literal space at the
  end of an ATX heading is stripped by the block parser, so the trim looked unreachable.
  It is reachable: `## &#32;Entity space` decodes the entity after the block parse, and
  the text node begins with a space that survives.
- `ArrayItemRemoval` on the exclude list survived, so nothing proved raw markup is
  dropped. `## foo <span>bar</span>` has to label as `foo bar` and slug as `foo-bar`.

A third escape, `MethodCallRemoval` on the listener registration in `EnvironmentFactory`,
was a genuine hole: the listener was constructed in the test's container stub but no test
dispatched the event through the factory's environment, so deleting the registration would
have passed the suite.

## Test tooling: two traps the HTTP tests hit

Both were measured while landing the transport tests (`#33`), and both had been
holding the gates hostage rather than reporting anything true.

### PHPUnit drops code a test did not declare

A test class declaring `CoversClass(A::class)` that also exercises `B` has the `B`
frames filtered out of the report as unintentional coverage. The suite executes
them, the report says it did not, and `codecov/patch` fails on code that has tests.

Measured: `phpunit --filter CurlHttpTransportTest --coverage-clover` reported
`Http/CurlOptions.php` with 45 statements and **none** covered, while the same run
was exercising the class on every rejection case.

`CoversClass` cannot be attached to a method (PHPUnit rejects the attribute:
"cannot target method"), and `CoversMethod` cannot name a private helper, so there
is no metadata escape hatch. The answer is placement: a rejection test belongs to
the class that raises the rejection. When it sits with the class that only
propagates it, the guard and its message escaping read as uncovered.

### A child process that is waiting makes a mutant time out

`LoopbackHttpServer::stop()` drained the child's pipes before closing, and the
child was parked in `stream_socket_accept()`. A mutant that made the request fail
early therefore left the parent waiting the child's full accept timeout, and
Infection reported a time out instead of a kill: 10.5 seconds to stop a server that
had served nothing.

Two changes, both measured:

- pass the command as an **array** to `proc_open`, so the process opened is PHP and
  not the shell that would be terminated while PHP kept the pipes open
- call `proc_terminate` before draining, since a child that answered has already
  exited and terminating a finished process does nothing

`stop()` went from 10.5s to 0.016s, and the transport test class from 3.5s to 1.1s.

The same trap in a different shape: the byte-capture test listened on a socket and
never answered, so the request only ended because the transport's own timeout
expired. That made the mutant removing the timeout option hang. A `raw` scenario now
answers with the bytes it received, so the mutant fails on the missing headers.
