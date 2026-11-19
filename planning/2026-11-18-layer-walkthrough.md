# Layer walkthrough: architectural record

**Date**: 2026-11-18
**Component**: `common-doc/commondoc`
**Branch**: `tyrsson-orchestration` at `0049a4c`

The owner reads each layer from app entry inwards and asks questions. This file
records **architectural decisions only**. Implementation detail lives in
`2026-11-19-layer-findings.md`.

**Why this exists**: the individual components are sound, but their composition
is not. Several layers were built to satisfy a gate or an error message rather
than to solve the problem the layer above poses, and the result is a chain of
compensations. This walkthrough is the sanity check.

---

## Layer 0: app entry

### Decisions

**D1. The package declares no binary.** `webware-console` owns the entry point.
The console integration is a single config contribution
(`ConsoleInterface::class['commands']`), proven by running upstream's binary and
seeing `commondoc:build` listed.

**D2. The entry point belongs to the consuming project, not the package.** The
binary `chdir`s to the consuming project's root, so every relative path later in
the process resolves there. `--output build` therefore means the consumer's
`build/`.

**D3. The bus, not the console, is the application.** The console is transport.
The pipeline, the middleware and the command listeners are where the build's
phases live, and a transport decision does not touch them.

### Open

- Is the `chdir` intended to be permanent for the process?
- Where does the site's own configuration live: the consumer's `config/autoload`,
  a command option, or a dedicated site file?

---

## Layer 1: console surface

### Decision

**D4. The console layer is transport only.** It declares a command definition,
dispatches one message on the bus, and reports the result. It holds no build
knowledge: no paths, no translation, no file system.

Its contract is set by `webware-console`, which reads a command's definition for
the input prompt and the help view, captures whatever the command writes, and
renders the returned status. A failure that is thrown cannot be rendered; a
failure that is returned can.

### Open

- **What does the report name?** The record holds local source paths, so the
  report says `Wrote guide/setup.md` while the file written is
  `guide/setup/index.html`. Three options: leave it, have the console derive the
  output path (giving the console a translation dependency), or record the
  written file (which the record's own design argues against). This is a layering
  question, not a formatting one.

---

## Layer 2: the bus entry

`BuildCommand` is constructed with `MessageBusInterface`, so its `handle()` call
is the boundary between transport and work. Everything below happens inside the
pipeline the bus owns.

### The shape

```
BuildCommand::execute()
  -> MessageBusInterface::handle(BuildSiteCommand)      alias -> MessageBus
    -> MiddlewarePipe::handle()                          shared service, built once
      -> Next closure over SplPriorityQueue
         CommandPreHandleMiddleware      priority  100
         SourceLoadingMiddleware         priority   50   (ours)
         MessageHandlerMiddleware        priority    1
         CommandPostHandleMiddleware     priority -100
         -> EmptyPipelineHandler
```

The pipe is assembled by `MiddlewarePipeFactory` from
`config[MessageBusInterface::class]['middleware_pipeline']`, ordered by an
`SplPriorityQueue`. The handler is not in the pipe: `MessageHandlerMiddleware`
resolves it through `config[...]['command_map']` and then `$container->get()`,
and checks that what came back is a `ResultInterface`.

### Decisions

**D5. Per-build state travels on the message or is produced inside `handle()`.**
`MiddlewarePipe`, every middleware, and the handler resolved from `command_map`
are container services, so one instance serves every invocation. Nothing
per-build can be injected into the handler.

---

## Layer 3: the handler and the CommonMark environment

The environment is CommonMark's, and CommonMark already defines how a package
extends it. Two measured facts decide the shape:

- `addExtension()` calls `configureSchema()` immediately and defers `register()`
  to first use, and every builder method calls `assertUninitialized()`. The
  environment must therefore be fully assembled before the first parse; there is
  no later hook.
- `Environment` takes its whole config in the constructor and `mergeConfig()` is
  deprecated. Config is a construction argument, not ambient state.

### Decisions

**D6. CommonMark behaviour enters through extensions, not through listener calls
in our factories.** `LinkRewriter` becomes a `ConfigurableExtensionInterface` that
registers its own `DocumentParsedEvent` listener. Extensions are the only shape
config-manager can govern: a consumer can name, order, or drop one from config,
where a listener added in our code is invisible to it. They are also the only
shape that can register renderers, parsers and delimiter processors, so a
requirement to reach further into CommonMark's processing does not arrive as a
new mechanism.

**D7. An extension's construction data is an argument; only its policy is
config.** Two classes of extension: *configured* (singleton from
`ExtensionPluginManager`, options read from its own config section) and *scoped*
(constructed per build from that build's own scan). A build's file lists are the
scoped extension's constructor arguments and never enter the config tree; only
policy belongs in `configureSchema()`.

**D8. Environment construction is a service, not a mode.**
`MarkdownConverterFactory` has a second mode only because a per-build object could
not be injected. A builder service, injected into the handler, produces the
per-build environment from the base composition plus the scoped extensions. The
container then has one composition rather than two, and the handler no longer
needs a locator.

### Open

- `SourceLoadingMiddleware` is a pass-through at priority 50 while the handler
  performs the scan itself. One of the two is redundant.

**D9. CommonMark's contract is a string in and a tree out.**
`ConverterInterface::convert(string): RenderedContentInterface`,
`MarkdownParserInterface::parse(string): Document`,
`DocumentRendererInterface::renderDocument(Document)`. The `Document` exists only
after a parse. Nothing in that boundary knows of a file, path, root, asset or
build, so discovery cannot be a step inside conversion. It is a step in front of
it, which makes `SourceLoadingMiddleware` the correct home and the handler the
wrong one. The middleware is not a placeholder for handler work; the handler
holding the scan is the misplaced step.

**D10. The tree is an object graph of typed nodes, each carrying an open data
bag.** Every `Document` is `Node` objects: `Block` and `Inline` subtypes with
parent/sibling links and `iterator()`/`walker()`, and every `Node` has a public
`Data $data`, seeded with `attributes`. So a node is three things at once: a
class a renderer can key on, a position in a structure, and a place to put data.
That is the whole extension surface, and it is why our vocabulary can ride on the
tree without CommonMark learning it: `commondoc.page` lives in `$document->data`,
and no CommonMark type names a page. It also means a requirement CommonMark has no
node for (a site-specific element, an admonition) is met by declaring a node plus
its renderer, not by post-processing HTML.

**D11. The tree is the document, semantically. Renderers own the markup.**
After `parse()` the source string is discarded and every consumer reads only the
tree, so the tree is the document as far as CommonMark is concerned. It carries
meaning and structure; HTML exists only in the renderers. That split is why
`DocumentViewRenderer` can return a view for the `Document` node instead of a
string without fighting the library. Reference definitions are not nodes: they
resolve into `Document::getReferenceMap()`, and blocks carry start/end lines, so
provenance is available to a report that wants to name a line.

**D12. CommonMark's unit of work is one document, so a build is N invocations of
a one-document engine.** `parse()` takes one string and `renderDocument()` one
tree; there is no batch call and no site concept. No extension ever sees a second
document. Therefore cross-document work (discovery, prev/next, a site index)
cannot live in an extension and must be complete before the first parse, while
per-document work can.

The environment is reusable across documents, and the library's own per-document
concern proves the idiom: per-document unique slugs are a `DocumentParsedEvent`
listener at priority -1000 that clears its history, on a **shared** environment.
Shared environment, per-document event, never a rebuilt environment. This is the
pattern `LinkRewriter` follows.

---

## Layer 4: phases before the handler

The bus is the application, so a build is a **pipeline of phases**, not a method.
Anything that must complete for every build before a document is parsed belongs
before the handler, and anything that must happen for every build after the
handler belongs after it. The handler is one phase among several, and the smallest
one.

### What must already be true when the handler runs

**D13. The scan is complete before the handler, and it is published for the
handler to read.** Today `BuildSiteHandler` both produces and consumes the scan,
which is why it is the whole application in one object. Discovery is
cross-document work (D12) and answers a question CommonMark has no vocabulary for
(D9), so it happens ahead of the handler.

Concretely, by the time the handler runs:

1. **The request has been validated.** A source root that is not a readable
   directory is refused in a phase, not midway through a loop, so the handler
   never has to carry a `Tree::at()` refusal in its union.
2. **The paths have been read once** and partitioned into documents and assets.
3. **The document set is known in full**, which is the precondition for every
   cross-document feature and the reason the order is fixed.
4. **The per-build environment can be composed**, because the lists it needs now
   exist. This is the phase D8 was pointing at.

### What the handler is then left holding

A loop per kind over data it did not have to find, plus the record. Encoding each
document and copying each asset. Nothing is discovered, nothing is partitioned,
nothing is validated.

### Decisions

**D14. Assets are not part of the render phase.** Copying an asset needs no
environment, no parse and no tree, so it does not belong in a loop that exists to
drive CommonMark. It is a separate phase over the same scan.

**D15. Per-build working state travels forward on the message.**
`PipelineHandlerInterface::handle()` takes a `MessageInterface`, so a phase hands
off by passing an enriched message to `$next->handle()`, not by storing state on
itself. `BuildSiteCommand` is immutable and holds two strings, so it needs the
`with...` form that returns a new instance carrying what the previous phase
found. No separate carrier type is required: the message is the carrier.

**D16. Reporting is a phase, not a return value.** `CommandPostHandleMiddleware`
runs at priority -100 and sees the `CommandResult`, so what a build reports is
produced after the handler, where a report can read the record without the handler
knowing what a report is. The handler's job ends at the record.

**D17. Two channels, both built into the bus: message forward, result back.**
`process(...): ResultInterface`, so a phase either passes through
(`$next->handle($message)`) or short-circuits by returning its own result.
`ResultInterface::getResult(): mixed` and `CommandResult`'s `result` is `mixed`,
so the result payload is unconstrained: the result carries whatever object is the
outcome, and `BuildRecord` is that object here, already exposing `input` and
`asset`.

**Retraction.** An earlier revision of this record claimed the result is "a result
value object, not a handoff channel" and that "discovery is published, not
returned". Both are false, and this package's own code disproves them: the scan
travels back inside `BuildRecord::input` and `BuildRecord::asset`.

The distinction is directional, not categorical. Working state goes forward on the
message. The outcome comes back on the result.

Two consequences:

- Refusal is a result, not an exception. A phase that rejects the request returns
  a failed result and never calls `next`, so the handler is not reached and
  `getStatus()` carries the outcome. This is the bus's own idiom for what
  `Console\BuildCommand` currently does by throwing.
- A `mixed` payload narrowed to one concrete class at a call site couples the
  caller to the handler's current return type. `BuildCommand` narrows to
  `BuildRecord` and refuses anything else; that is a decision about the console
  layer, not a constraint the bus imposes.

---

## Layer 5: publication

Measured state: `common-doc/commondoc` is public, `has_pages` is `false`, the
default branch is `1.0.x`, and `.github/workflows` does not exist. The
`Dockerfile` present today is a **development tooling image** (PHP, Composer,
Mago, Xdebug, `command: sleep infinity`), not a publisher.

### Decisions

**D18. Publication is an Actions artifact, not a `gh-pages` branch.** A mesh of
reasons, and they all point the same way:

- The branch name is not required. Pages accepts any branch plus a folder (`/` or
  `/docs`), so `gh-pages` is convention only.
- The `/docs` folder option **collides with our source tree**. Sources live at
  `docs/v1`, so "deploy from `main` / `/docs`" would publish the Markdown inputs
  as the site.
- A branch deployment runs Jekyll, which drops every path beginning with `_`.
  Our output is ours; it should be served byte for byte.
- The artifact route has no branch to keep in sync, so there is no second copy of
  the site in git history.

**D19. The output directory name is not part of the contract.** The artifact step
uploads whatever directory the build wrote, so `build/` is correct and nothing
needs to be named `gh-pages`.

**D20. A project site publishes under its repository name, so the base path is
config, not code.** `https://common-doc.github.io/commondoc/` means
`DocumentRoot::basePath` is `/commondoc/` for this repository's own site and `/`
for an organization site or a custom domain. This is exactly the single value
`DocumentRoot` was built to receive; nothing else about the build changes.

### Two audiences, deliberately not merged

The brief calls for a **published image** and an Action. Those serve different
consumers:

- this repository building its own docs needs only `php vendor/bin/webware`;
- another repository building its site should not need a Composer install of this
  package plus the whole Webware fleet, so it pulls an image and runs one command
  in it.

The second is the reason the image exists. The first does not need it.

---

## Layer 6: one site, many source roots

The package is described as a **multi-repository** builder. The shape of that is
one site composed from many repositories, not many sites.

**Retraction.** An earlier revision of this layer described multi-site and
warned that `DocumentRoot`, `PageContext`, `RouteCollector` and the environment
would collide in one process. That premise was wrong, and the conclusion with it.
One site means one `DocumentRoot`, one base path, one output directory, one
address space and one record. Those being per-build singletons is correct.

### Decisions

**D21. Inheritance must never be the extension route.** `BuildSiteHandler` owns
discovery, validation, environment composition, both encode loops and the record.
Against that shape the only way to add a capability is to subclass and override
`handle()`, which is not composition. The cause is not the handler's size; it is
that the handler is the only place anything happens. When one object holds every
concern, adding a concern has nowhere to go except into it or on top of it.
Extension is by **adding a phase**, never by replacing an object. That is the
operational meaning of "the bus is the application" (D3).

**D22. The axis is per root, not per site.**

| Scope | What lives there |
| --- | --- |
| per site | base path, output directory, route collector, record, environment policy |
| per root | the walk, and the mount prefix placing it inside the one address space |
| per document | page, parse, rewrite |

A site definition is a set of (source root, mount prefix) pairs. The scan walks
each root and prefixes what it finds; the filters then run **once** over the merged
list. That is already their shape: `Tree::at()` produces paths relative to a
single root and `Markdown::scan()` takes a bare list, so nothing below the scan has
to know there was ever more than one root. The mount prefix is the whole mechanism
that turns N roots into one address space.

**D23. Cross-repository links are why discovery must span every root before the
first parse.** A link in one repository may target a document in another, so the
document set has to be complete site-wide before any conversion, and the
rewriter's membership sets are the union. With a single root, early discovery was
a latent bug; with many roots it is an immediate one. This is D13 made
non-negotiable rather than tidy.

### Consequence

A new repository is a new entry in the site definition. It is not a new message, a
new handler, or a subclass. No phase may assume it serves one root, and no type
below the scan may assume the paths came from one.

---

## Proposed next changes

Ordered by what unblocks the most. Proposals, not decisions.

Taken together, P1 to P4 are the whole of multi-root: two lines of coupling (see
D24) plus the phase structure that lets a validated list reach the scan. There is
no component to build.

**P7. Introduce `SiteConfig`, built per run.** Revised: as first written this had a
timing hole. `site.json` merges at boot into the config service; CLI overrides
arrive later, inside `execute()`. One boot-time factory cannot see both. So
`SiteConfig` is a per-run value built from the config tree plus the overrides, and
carried on the message like everything else per build. It replaces the two
`section()` readers in `DocumentRootFactory` and `PageFactory` by becoming the
single validated read of the site section.

**Resolved by D32:** the override surface is roots and output only. `basePath` is
boot-fixed, so `DocumentRoot` stays a singleton and P7 is a small change.

**P1. Make the phases real.** Move validation and the scan into
`SourceLoadingMiddleware`; give assets their own phase; give reporting its own
phase after the handler. `BuildSiteHandler` then holds the encode loops and the
record, and nothing else. This is the change that makes multi-root a config entry
instead of a handler rewrite, and it is the precondition for P2 being small.

**P2. Turn `LinkRewriter` into a `ConfigurableExtensionInterface`.** One addition
deletes four compensations: the second mode of `MarkdownConverterFactory`, the
`ServiceLocatorInterface` coercion inside it, the `ServiceLocatorInterface` in the
handler, and the concrete-`Environment` return on `EnvironmentFactory` together
with the comment that justifies it.

**P3. Let the message carry the roots.** `BuildSiteCommand` becomes a request over
a list of (root, mount) pairs with `with*` builders, so a phase enriches it
forward. This is D15 made concrete, and it is where the site definition lives.

**P4. Give `DocumentRoot` the root-to-mount mapping** so a scanned path can be
translated into the one address space. Today it assumes a single root, which is
the only thing in the package that assumes one.

**Landed, and it needed no change to `DocumentRoot`.** The mount is applied to the
discovered path before anything reads it, so the translation still takes one path
and answers one address. That is D24's cardinality working: a composed site is the
same code path with a longer list.

Two things learned while landing it:

- `SiteConfig` owns the exclusivity invariant, because two roots whose mounts
  overlap leave the owner of a path to iteration order.
- **A root mounted at `/` cannot coexist with any other root.** That is correct
  rather than a limitation to work around: `/` claims every address. A composed
  site therefore mounts every root at a name. Where a landing page at `/` comes
  from is undecided.

**P5. Record the output path, not only the source path.** `Wrote index.md` does
not say where it went, and the report phase cannot compute it without
`DocumentRoot`. Storing the written file in the record makes the report a
formatting step and removes the ambiguity measured in the first build.

**P6. Capture the end-to-end build as a test.** There is no test that builds a
real tree and inspects the output; `BuildSiteHandlerTest` is 428 lines of doubles.
`docs/v1` now exists and was verified by hand: build to a temp directory, assert
the exact output tree, and assert the rewritten links. That is the regression net
for every change above.

**D24. Multi-root is configuration, not a component.** Measured: `src/` contains
**zero** occurrences of `repo`, `repository` or `multi`, and exactly **one**
single-root assumption:

```
BuildSiteHandler.php:168    $paths = Tree::at($source)->paths();
```

with `BuildSiteCommand::getSource(): string` as its transport. That is the whole
of the coupling. Nothing else in the package knows how many roots exist, and no
type needs to learn that a repository is a thing.

So the capability is a **cardinality**, not a feature. One root and many roots are
the same code path with a list of length one or more. The site definition is an
entry in the config tree; a new repository is a new element in that list, with no
new class, no new phase, no new message and no subclass. A separate component for
"multi-repo" would exist only to hold a list.

Configuration is also where the mistakes are caught early: two roots mounting at
one prefix, or a mount without a leading slash, are refusals at build time from a
validated value object, not a surprising address on the published site.

The division of labour:

| Concern | Where |
| --- | --- |
| which roots, mounted where | config |
| validation of that list | one factory, producing a typed value |
| walking the roots | the scan phase |
| placing a path in the one address space | `DocumentRoot` |
| the page and its URL | per document, as now |

The type boundary matters: config is the input format, and a validated object is
the contract the scan receives, so raw arrays never reach the scan and a bad list
cannot travel further than the factory that read it.

**D25. One `SiteConfig` value object holds everything site-level, and it is the
only reader of that config.** Today two factories independently read a section
and apply their own fallbacks:

```
DocumentRootFactory.php:102    $section = self::section($container->get('config'));
PageFactory.php:134            $section = self::section($container->get('config'));
```

Three overlapping reads of two sections, each with a private `section()` and a
private default. `SiteConfig` collapses them into one validated read, and the
factories stop knowing that a `config` service exists.

What it holds is exactly what is constant for one site, which is the same rule
D22 already established:

| Field | Why it is site-level |
| --- | --- |
| base path, path type | one address space |
| project name, slug, version label | every page in the build shares them |

The roots and the output directory are site-level too, but they are added by P3
rather than here: they are the fields a command line may override, and this type is
built at boot.

`Page` keeps those three identity fields because templates read them flat, but
`PageFactory` copies them from `SiteConfig` rather than reading config itself.
Per-document values (local path, label, address) stay where they are.

Two properties were settled while implementing it:

- The section is keyed by `SiteConfig::class`, one section rather than two.
- The fallback is **per key**, not per section. A whole-section fallback would
  discard a correctly written base path because an unrelated key was missing, and
  that is the one failure a deployment cannot see: it would publish at `/` and look
  correct on disk.

**D26. CLI options are overrides, not a second input path.** A command run has
exactly one configuration, produced by one precedence rule:

```
CLI option  >  config tree  >  built-in default
```

Revised: as first written, the overrides mutated `SiteConfig`, which cannot work.
A field a command line may override cannot live on a boot-time object, because the
override arrives after the object exists. So `SiteConfig` carries only what a
command line cannot change (D32), and the overridable fields (roots, output) belong
to the build request on the message, where P3 puts them.

That split is what removes the timing hole rather than papering over it: two
lifetimes, two types, and no object holding a value that can still change after it
was built.

This changes the command surface, and the change is the point: with the roots in
config, `source` stops being `REQUIRED` and becomes an override. A run with no
arguments builds the configured site, which is the majority case (D27).

**D27. The Action path uses configuration only, so the command must work with no
arguments.** "Vast majority of runs are the Action from set configuration" is a
requirement on the command definition, not just an observation. An Action that has
to pass paths has moved the site definition out of the repository and into YAML.

**Retraction.** An earlier revision claimed `--source` "cannot express a list" and
that it "must not be extended into syntax for mounts". Both are false. Symfony
supports `InputArgument::IS_ARRAY` and `InputOption::VALUE_IS_ARRAY`, and
`webware-console`'s input prompter reads `isArray`, so a repeated option is a
supported list of roots for a run. It is simply not a version 1 requirement.

**D28. Version 1 composes a site from mechanisms that are already proven, and there
are two of them.**

| Mechanism | Where the site definition lives |
| --- | --- |
| a custom property set on the organization's repositories | GitHub metadata |
| a `site.json` manifest in the consumer repository that holds the Action YAML | that repository |

Both end in the same place: the roots list inside the config tree. Neither needs a
new build concept, which is D24 again from the input side.

**D29. `site.json` merges into the config service with no new reader.** Measured
in `webware-configmanager`: `Reader\Json` is registered under the `json` alias,
`Factory::fromFile()` and `fromFiles()` read and merge through `ConfigMerger`, and
`AbstractReader::withIncludes()` resolves an `@include` directive recursively, so a
manifest can include fragments and a later file wins. `Factory::fromProviders()`
also accepts any callable, so a JSON file becomes a provider without a new class:

```php
static fn (): array => Factory::fromFile(__DIR__ . '/site.json')
```

This is the capability that makes the manifest route cheap, and it is why the
config-manager swap matters: `ConfigAggregator` had no such reader.

**D30. The package never fetches a site definition; the Action adapts metadata into
config.** Org custom properties live behind an API. Putting that call in the
package would give a build a network dependency, a credential dependency and a
failure mode that a local run cannot reproduce. The Action reads the properties and
writes a config file; the package reads config and does not know the difference
between a manifest a human committed and one a workflow generated.

The boundary: **network and credentials belong to the workflow, config belongs to
the package.** A local run and a CI run then execute identical code, which is what
makes CI failures reproducible.

### Precedence, restated with the manifest in place

```
CLI option  >  config tree (site.json, autoload, development)  >  default
```

One rule, unchanged by how many mechanisms feed the config tree.

---

## Extension-point audit

The purpose of this walkthrough is to confirm the extension points exist before the
required changes are made. Measured, one row per point.

| Point | Mechanism | Config-driven | Exists |
| --- | --- | --- | --- |
| bus middleware | `middleware_pipeline` list | yes | yes |
| bus events and listeners | `listeners` key, per-event lists | yes | yes |
| CommonMark extensions | plugin manager + its config section | yes | yes |
| command to handler | `command_map` | yes | yes |
| console commands | `ConsoleInterface::commands` | yes | yes |
| **message as carrier** | `BuildSiteCommand` | n/a | **no** |
| **site definition** | `SiteConfig` | n/a | **no** |

**D31. The pipeline's extension points exist; the data's do not.** A phase has a
place to run and nowhere to put what it produced. The two gaps are exactly P3 (the
message gains `with*` builders) and P7 (`SiteConfig` exists and is validated), which
is why those two are the precondition for a fetch phase rather than follow-up work.

### Confirmed properties of the points that do exist

**Middleware accumulates across packages.** `ConfigMerger::applyValue()` appends an
integer key rather than recursing, so a consumer's `middleware_pipeline` entry adds
to ours instead of replacing index 0. Priority order is then global:
`100` pre-handle event, `50` source loading, `1` handler, `-100` post-handle event.
A fetch phase is any priority above `50`.

**Listeners accumulate** the same way, keyed by event class.

**Two hazards, both measured:**

- `MiddlewarePipeFactory` calls `has()` before `get()` and `continue`s on a miss, so
  an entry whose factory is missing or misspelled is **silently skipped**. A phase
  can be absent and nothing reports it.
- `ExtensionPluginManager::getDefaultExtensions()` returns
  `array_keys($invokables)` then `array_keys($factories)`. Every built-in is an
  invokable and every consumer extension is a factory, so a consumer cannot order
  its extension relative to a built-in. Ordering is declaration order within a
  bucket, not a priority. `@include` and merge directives order *files*, not
  extensions.

### Where a fetch phase sits

Between the pre-handle event and source loading, so it is the first thing that
knows the request and the last thing before anything is read from disk. It needs
`SiteConfig` to exist (to know what was configured) and the message to carry the
result (to hand roots to the scan). Neither is a new concept: both are D24 and D15
becoming real.

---

## Standing corrections to earlier work

Recorded because the same mistake recurred, and the pattern matters more than any
single instance.

- The per-build environment, the second converter mode, the service locator in
  the handler, and the page listener all compensate for one root cause: per-build
  runtime data was expressed as environment configuration.
- `LinkRewriter` is a pass over a parsed document. **Retracting** an earlier claim
  that it belongs between `parse()` and `renderDocument()` in the coordinator and
  "not on an event". The hook was never the problem: `DocumentParsedEvent` is
  CommonMark's own hook for exactly this. The problem was the registration
  vehicle, a mode of a container factory. The coordinator-driven version would
  have answered the symptom by pulling Markdown plumbing into the coordinator and
  putting the rewriter outside config's reach. See D6.
- Middleware and listeners with empty bodies are seams in the bus, not dead code.
  A transport change is not a reason to remove them.

### The composition defect, stated once

`Environment` is long-lived and configured once; the document and asset lists
exist only while a build runs. Registering the rewriter as an environment
listener forced the environment to become per-build, which forced the converter
to become per-build, which forced the handler to hold a container. Remove the
listener and the whole chain disappears.
