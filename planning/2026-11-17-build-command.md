# Session record: making `commondoc:build` real

**Date**: 2026-11-17
**Component**: `common-doc/commondoc`
**Branch**: `tyrsson-build-command` (off `6c9b626`)
**Status**: design accepted. The input scans, the translation, the record, the
link rewriter and the render seam are implemented and tested. The handler wiring
remains, including registering the rewriter on the environment it needs.

This record exists because the proposals were delivered in chat and chat is not
durable. It is the reference the implementation is reviewed against.

`aggregate-renderer` is treated as greenfield throughout. The owner: "Pretend
like there is no code there because we can completely rewrite it if needed." No
decision in this record rests on aggregate's current code.

---

## 1. Why this work exists

`commondoc:build` is advertised in the console menu and does nothing. Measured:
`php bin/commondoc commondoc:build <dir> --output <dir>/out` exits 0 and the
output directory is never created.

| File | Current state |
|---|---|
| `src/CommandHandler/BuildSiteHandler.php` | returns `result: []` unconditionally |
| `src/Middleware/SourceLoadingMiddleware.php` | `return $next->handle($message)` |
| `src/Listener/CommandPreHandleListener.php`, `CommandPostHandleListener.php` | empty bodies |

The rendering seam underneath is complete and tested: `DocumentViewRenderer`
delegates to `ChildNodeRendererInterface`, `Page` is a readonly `ModelInterface`,
`PageContext` is a single slot, the template map registers `commondoc::document`
and `commondoc::layout`, and the theme resolver is aggregated. The library half
works. The command half does not.

---

## 2. Settled decisions

Decided by the owner. Not open for reconsideration.

1. **`webware/webware-filewriter` is added to `require`.** Installed today only
   transitively through `webware-configmanager`.
2. **Writes go through `FileWriter::writeTree()`.** It creates the parent
   directory chain and delegates to the atomic temp-file-and-rename `write()`.
3. **Naming is `Input` for markdown and `Output` for html.** Owner: "Or we can
   use Input === markdown and Output === html".
4. **`\Build` is rejected as a namespace.** Owner: "what does it represent? Is
   it a noun or a verb? Is it the 'local' or the 'web'?"
5. **No plural in a type name.** Owner: "I absolutely HATE plurals in
   programming. `CommonDoc\Input\MarkdownFiles`. See how much better that fits?
   Its not binding us to a count." A type is named for what it represents, never
   for how many of something it holds.
6. **The speckit documents are guidance, not binding.** No proposal here rests
   on an FR number as its justification.
7. **The published path is the shared vocabulary.** "Since we know the local
   paths and they translate to the web path, we will refer to that as the
   published path from now on."
8. **Deployment is configured, not assumed.** The site must support a custom
   domain, an organization or user site, and a project site, from one build.
9. **Links are resolved through the seeded router, not string replacement.**
   The translation feeds `webware-navigation` and `mezzio` routing so templates
   render links exactly as they would in a web application.
10. **There is no performance budget.** Owner: "this is being built on a cli
    during a action run. It doesnt have to be blazing fast because the actual
    rendering will be done from the resulting files, not as they are being
    built." Correctness and legibility win every tie.
11. **The render cache is dropped.** A full, non-incremental build fits the CI
    job budget, so incremental reuse is complexity with no payoff and several
    ways to publish a wrong site.
12. **The log sink is chosen in configuration, not in commondoc.** Owner: "Im
    having the log session add a stream handler factory so we only have to map it
    in config." webware-log gained a `StreamHandlerFactory` and a `logFile`
    default in `2.0.0-beta.1`, so this package registers a handler and states a
    path rather than building loggers.
13. **No snake_case anywhere, including configuration keys.** Owner: "we never
    use snake case since if those config values or keys become variables they
    then violate the PER coding standard." A key such as `logFile` is correct in
    camelCase because the key may become a variable, and a snake_case variable
    violates PER. This governs every new key and identifier this work introduces.

    Why it cannot work the other way: PHP matches constructor unpacking, named
    arguments, promoted properties and `extract()` by identifier name. A
    `log_errors` key therefore forces a `$log_errors` parameter or property, which
    is the violation. The only escape is string-literal array access, which keeps
    the key an untyped string with no completion, no typo safety and no way to
    pass the array into a constructor. Every other route needs a hand-written
    `$logErrors = $config['log_errors'];` at each crossing, and that map is a
    second place where key and identifier must stay in sync. camelCase makes them
    the same token, so a config array spreads straight into a value object.

    One exception: an external contract we do not control, such as a third-party
    API response, gets a single mapper at the edge.

---

## 3. The two perspectives and the translation

The whole design rests on one observation: a documentation site is the same
thing as a directory mounted as an Apache `DocumentRoot`. A filesystem root is
mounted at a URL prefix, and every file beneath it has exactly one published
path.

Three values describe one document:

| Perspective | Example | Where it comes from |
|---|---|---|
| Local path | `guide/setup.md` | the source tree |
| Published path | `/guide/setup/` | the translation |
| Output file | `guide/setup/index.html` | the translation |

`published path = basePath + (local path, suffix mapped)`. The output file is the
published path with a leading slash dropped, and the same path type applied.

### One translation, two call sites

Markdown sources contain links: `[setup](guide/setup.md)`. Published unmodified,
that link either 404s or serves raw markdown. So the translation is needed
**during rendering**, not only when choosing an output file.

| Call site | Question |
|---|---|
| Write pass | where does this document's file go? |
| Render pass, per link | what published path does this target have? |
| Render pass, per image | what published path does this asset have? |

Same function, same inputs. That is why this belongs in one seam, and why the
renderer must take it as a collaborator rather than doing a post-render string
replace.

### Route name is the local path

The route name, the document list's key, and what a markdown link already
contains are all the local path. This collapses two key spaces into one:

- Name-keyed access: `generateUri('guide/setup.md')`, navigation, breadcrumbs.
- Path-keyed access: a markdown link's `href`.

Without this there are two identifier schemes to keep in sync, which is the
class of defect that produced the code being replaced.

### What the translation must handle

| Input | Requirement |
|---|---|
| `./setup.md`, `../setup.md`, `/guide/setup.md`, `setup.md` | resolve against the current document's local path before lookup |
| `guide/setup.md#install` | fragment survives; only the path part is mapped |
| `guide/setup.md?x=1` | query survives |
| `https://elsewhere.example/x` | untouched |
| `![d](../images/d.png)` | assets are translated and published too, not just documents |
| a link to a document that does not exist | a build error, not a published dead link |

---

## 4. Deployment configuration

All three targets differ in exactly two values.

| Target | basePath | host |
|---|---|---|
| Custom domain | `/` | `docs.example.com` |
| Organization or user site | `/` | `<owner>.github.io` |
| Project site | `/<repositoryname>/` | `<owner>.github.io` |

So the configuration is a base path plus an optional hostname, and the published
path is `basePath + mapped local path`. Aggregate's `/{slug}/{version}/` is the
same field with a longer value, which is why aggregate supplies a prefix rather
than a second translation.

There is no branching on deployment type. One field, and the same value serves
relative and absolute links.

---

## 5. GitHub Pages constraints

Checked against the GitHub Pages documentation rather than recall.

| Constraint | Consequence |
|---|---|
| Project sites are served at `/<repositoryname>/`; user and org sites at `/` | the base path is configuration, never a constant |
| The deployed artifact must have `index.html` at its top level | a build without one publishes nothing; enforce or synthesize it |
| Jekyll excludes `/vendor`, `/node_modules`, `_*`, `.*`, `#*`, `*~` by default | generated paths can be silently dropped; `.nojekyll` at the artifact root opts out |
| Pages serves from Linux, so paths are case sensitive | a Windows checkout resolves `Guide/Setup.md` that 404s on Pages; validate case rather than discover it in production |
| `404.html` and `CNAME` must sit at the artifact root | placement rule, not new work |

The `.nojekyll` behaviour was not re-confirmed in this session, only the exclusion
list.

---

## 6. The router-seeded build

The owner's design: the translation branches on the deployment configuration,
produces the published path, that data is written to a file using
configmanager, and it feeds the seeded router. Templates then use
`webware-navigation` to build links exactly as in a web environment.

### Why this is the right shape

Templates contain no build-specific code. `$this->navigation('docs')->menu()` and
`generateUri()` work identically in a build and in an HTTP request.

Confirmed in source, not assumed:
`NavigationItem::fromRouteOptions` builds from `Route::getOptions()` reading
`label`, `parent`, `order` and `navigation`, and `Navigation::__invoke`
iterates `RouteCollectorInterface::getRoutes()` keyed by route name. A seeded
collector is therefore sufficient.

### The one hard problem, and its resolution

The router lives in the container, which is built from `config/config.php`
before the command runs. The published-path table depends on files discovered
after it starts. A router seeded from a file written by the previous run works
for the *next* build and fails for the current one.

Resolution: the container holds a **collector**, not a frozen route set. The scan
pass adds routes; the render pass reads them. Because there is no performance
budget (decision 10), this is simply "do the passes in order" rather than
anything subtle.

### The collector was not resolvable until it was wired

Reading the source rather than assuming exposed a gap: neither the collector nor
a router was registered anywhere in this package. `RouteCollectorFactory` refuses
to build without `RouterInterface`, and `Webware\Router\ConfigProvider` registers
`QueryParamRouter` as a factory but ships its own `RouterInterface` alias
**commented out**, so the interface resolved to nothing.

Two entries fix it, both in this package's `ConfigProvider`:

| Entry | Why it is ours |
|---|---|
| `RouterInterface::class => QueryParamRouter::class` | upstream registers the factory but not the alias |
| `RouteCollector::class => RouteCollectorFactory::class` | nothing in the aggregated set declares it |

`RouteCollectorInterface::class => RouteCollector::class` comes with it, because
Mezzio's own code requests the interface.

`Mezzio\Router\ConfigProvider` was considered instead of restating those entries.
It declares the same two and then four dispatch middleware factories, and those
need `Mezzio\MiddlewareFactoryInterface`, which arrives only with
`mezzio/mezzio`. That package is not installed and this build has no HTTP
pipeline, so aggregating the provider would register four services the container
cannot build in exchange for saving two lines. Two declarative lines naming
upstream's own factory class are not the same defect as a table re-implementing
upstream's logic.

`mezzio/mezzio-router` is now a direct requirement, because `src/` names its
classes. It was previously transitive, through `query-param-router` and
`mezzio-laminasviewrenderer`.

One thing that is not ours: `RouteCollectorFactory` reads
`config['router']['detect_duplicates']` while `QueryParamRouterFactory` reads
`config[QueryParamRouter::class]['detect_duplicates']`. Same concept, two keys,
and setting one does not set the other. Both default to true, which is what the
build wants.

### What the build does, in order

1. **Read configuration**: source, output, base path, optional host.
2. **Scan**: one `Tree` walk, then `Markdown` and `Asset` filter the result.
3. **Translate and seed**: for each document, ask `DocumentRoot` for its
   published path and its output file, then add one route to the collector named
   by local path. Collisions are refused by the collector.
4. **Render**: convert each document, with the translation available to link and
   image resolution.
5. **Write**: output file per document and per asset.
6. **Report**: what was written and what failed.

Translate and seed are one pass because they are one act: the published path is
the route path, so computing it and registering it are the same step. Splitting
them would mean computing the path twice or storing it between passes.

Collision detection is not new work. Two local paths mapping to one published
path is a duplicate route, and `Mezzio\Router\DuplicateRouteDetector` already
detects that. The collector owns it; nothing in this package re-implements it.

### Costs, stated honestly

- Every document and asset becomes a route. For one repo that is nothing; for
  aggregate across a large corpus it is tens of thousands of routes. Decision 10
  makes this acceptable, and it is a reason to prefer plain route objects over
  deep per-route hierarchies.
- Assets need the same translation as documents, with a copy instead of a
  render.

---

## 7. Proposal A: inputs and the translation

### `CommonDoc\Input\Markdown`

```php
public function scan(array $paths): list<string>
```

Selects `*.md` from a path list. Order inherited from the list. Paths relative to
the root, `/` separated, as `Tree::paths()` produces them.

Superseded signature: this was `scan(string $root): list<string>`, walking the
tree itself. See "One traversal, then partition" for why the walk moved out.

An asset counterpart is on the same terms, since images are published too. It is
a difference against the documents rather than a second suffix rule, so the two
lists are total and disjoint by construction.

### The translation type

`CommonDoc\Output\HtmlPath` from the earlier draft is **withdrawn**. A pure
suffix swap cannot express a base path or a URL style, so it cannot be the single
translation.

Proposed replacement, name open:

```php
final readonly class DocumentRoot
{
    public function __construct(
        public string $basePath,   // '/' or '/repositoryname/'
        public PathType $pathType, // Directory or File
    ) {}

    public function published(string $localPath): string;  // '/guide/setup/'
    public function file(string $localPath): string;       // 'guide/setup/index.html'
}
```

`DocumentRoot` is taken from the owner's own analogy and names what it is: a
local root mounted at a URL prefix. The alternative is `Deployment`.

`PathType` was settled by the owner after two rejected names. `Style` was
rejected because it reads as CSS. `AddressStyle` was rejected because in a
program where `address` also means a memory location, the word pulls the wrong
way. Both cases are paths and they differ only in whether the path names a
directory or a file, so the type says exactly that, and `File` replaces `Plain`
because a positive definition beats "not a directory".

### Path type

| `PathType` | `guide/setup.md` gives | File |
|---|---|---|
| `Directory` | `/guide/setup/` | `guide/setup/index.html` |
| `File` | `/guide/setup.html` | `guide/setup.html` |

Recommendation: **`Directory`**, for three reasons. Pages serves it
natively. It survives a later move to a different host. And it keeps the mapping
independent of the file extension, so `.md` is the only suffix rule needed.

One hazard: `guide/setup.md` and `guide/setup/index.md` both map to
`guide/setup/index.html`, so one silently overwrites the other. That must be a
pre-render error.

Whichever type is chosen, **every internal link must match it**, which is why
the type belongs to the translation and not to the writer.

---

## 8. Proposal B: the build

`BuildSiteHandler` becomes the coordinator over the passes in section 6, taking
`ConverterInterface`, the scan, the translation and the router collector as
collaborators through `BuildSiteHandlerFactory`.

The single-pass loop in the earlier draft is **withdrawn**: a link to
`guide/setup.md` may point at a document not yet visited, so every route must be
registered before the first render.

Conversion stays through `ConverterInterface` from the container, so extensions,
the document node seam, the layout wrap and theme resolution all apply with no
new wiring.

### Link resolution

A CommonMark `DocumentParsedEvent` listener walks `Link` and `Image` nodes and
rewrites targets through the translation. Chosen over a post-render string
replace because it operates on parsed nodes with a known target, so it cannot
corrupt prose or attribute values.

#### The current document's own path

A relative target only means something against the document it was written in,
and at first the listener had no way to reach that path. The order was measured
rather than assumed:

| Event | Dispatched from |
|---|---|
| `DocumentParsedEvent` | `MarkdownParser::parse()`, line 108 |
| `DocumentPreRenderEvent` | `HtmlRenderer::renderDocument()`, line 40 |

`PageContextListener` wrote the page on the pre-render event, so at parse time
`commondoc.page` did not exist yet. Three ways to close that were weighed: the
rewriter takes `PageContext` as a collaborator, the rewriter listens on
pre-render, or the path is on the document before the rewrite runs.

The third was chosen by the owner, and it landed as a move rather than as new
state. `PageContextListener` now writes on the parsed event. The caller already
sets the page before `convert()`, so the page is available at parse time, and
document data survives into rendering, so `DocumentViewRenderer` reads the same
value one event later than it used to. Nothing new is carried and nothing has to
be kept in step.

The listener is registered with no priority, because a priority would be a claim
nothing can test. An arbitrary number is indistinguishable from its neighbours
unless a test depends on one of them, and the only listener that could is the
rewriter, which the build registers later than this factory runs.
`PrioritizedList`, which the dispatcher iterates, runs listeners of equal
priority in the order they were added, and every listener the build adds is
added to an environment the factory has already finished, so the page is written
first without a number saying so. The first version carried `priority: 10`, and
Infection reported it as two unobservable mutants, which is the same finding
that removed the renderer's priority earlier in this session.

`LinkRewriter` therefore takes the translation and the two lists, and reads the
source path from the document it was handed. Nothing it holds changes between
documents, which is what lets a test drive it with a bare `Document`.

#### What a target may be

| Target | Result |
|---|---|
| `intro.md` | resolved against the directory of the current document |
| `./intro.md` | the same, because `.` segments are dropped |
| `../errors.md` | resolved against that directory, then checked against the root |
| `/guide/intro.md` | resolved against the root, which is the only absolute space a reader has |
| `intro.md#install` | the path is mapped, the fragment is carried through |
| `intro.md?tab=2` | the path is mapped, the query is carried through |
| `#install` | untouched: there is no path to map |
| `https://`, `mailto:`, `//cdn` | untouched: a scheme or a leading double slash names another origin |
| a document or asset the scan found | published through the translation |
| anything else | the build fails, naming the document, the target and what it resolved to |
| a path that climbs above the root | the build fails rather than clamping to the root |

Both translations are asked, not just the document one. A link to an image has
the same problem a link to a document has, and `DocumentRoot::asset()` is the
second answer: an asset is copied where it sits, so its address is the base path
followed by its own path. Keeping that in `DocumentRoot` means a link to an image
and the file that serves it are produced by one type, exactly as for a document.

#### What it does not do

Three cases are absent deliberately rather than forgotten:

- **A target naming a directory** (`../guide/`) is not resolved, even though
  `guide/index.md` publishes at that address. Resolving it means guessing which
  index document the author meant, and the guess is wrong for any directory that
  has none.
- **A percent-encoded target** (`my%20file.md`) is looked up as written, so it
  does not match the path the scan reports. Nothing decodes it yet.
- **Target casing must match the scan.** Both lookups are exact, so a link
  written in a different case than the file on disk fails even on a
  case-insensitive filesystem.

#### Registration is not wired yet

`EnvironmentFactory` does not register the rewriter, and that is deliberate. It
needs the document and asset lists, which come from the traversal the coordinator
performs at build time, and no coordinator exists yet. Registering it now would
mean either a second traversal inside the container or an empty list that fails
every internal link. It belongs to the orchestration work, which is where the
lists are produced.

---

## 9. Proposal C: reporting

Corrected by the owner: the command reports what was written and what failed.
The log is a separate record for review, not the reporting channel.

`CommandResult::$result` is `mixed`, so it carries a record object:

```php
final readonly class BuildRecord
{
    /** @var list<non-empty-string> */
    public array $input;

    /** @var list<non-empty-string> */
    public array $output;

    /** @var array<non-empty-string, non-empty-string> */
    public array $failed;   // local path => reason
}
```

- Failures keyed by local path rather than a third list: one less concept than a
  list of pairs, and the invariant stays checkable (every key is in `input` and
  absent from `output`).
- Proposed namespace `CommonDoc\Record\BuildRecord`, symmetric with `Input` and
  `Output`.
- `$input` means "discovered". If ignore rules are ever added it must not be read
  as "attempted".

`BuildCommand` currently does `array_filter($raw, is_string(...))`, which would
print **nothing** for a `BuildRecord`. It must narrow `result` to `BuildRecord`,
and say so when it sees something else. Printing nothing on an unrecognised
result is exactly what let the empty build look successful.

---

## 10. Proposal D: the build log, mapped in config

`webinertia/webware-log` `2.0.0-beta.1` is tagged and verified. The shape is much
smaller than the earlier draft, because the separation of concerns landed in
webware-log rather than in commondoc.

### What the tagged release provides

Verified against the tag, not the working tree:

| Piece | Tagged behaviour |
|---|---|
| `ConfigProvider::getConfigDefaults()` | gains `'logFile' => 'data/log/{channel}.log'` |
| `LogDefaults` phpstan shape | gains `logFile: string` |
| `ConfigProvider::__invoke()` | contributes `LoggerInterface::class => getConfigDefaults()`, so `logFile` always resolves |
| `Handler\StreamHandlerFactory` | builds a `Monolog\Handler\StreamHandler` from `logFile`, substituting `{channel}` |
| `LogFactory` | pushes a `StreamHandler` when `Monolog\Handler\StreamHandler::class` is registered, keeping the conditional `PhpDbHandler` push |
| `Handler\PhpDbHandler::class` factory | **removed** from the default `factories` map, which was the defect fix |
| `Handler\StreamHandlerFactory` | **not registered**; the consumer registers it |

### What commondoc has to do

Two config changes and one registration. No new type.

1. Aggregate `Webware\Log\ConfigProvider` in `config/config.php`, which wires the
   `LogEvent` listener and contributes the defaults including `logFile`.
2. Register `Monolog\Handler\StreamHandler::class =>
   Webware\Log\Handler\StreamHandlerFactory::class` in commondoc's
   `dependencies.factories`.
3. Leave `logFile` at its default. `data/log/{channel}.log` already suits this
   package and resolves outside the output tree.

### Dependency

`composer.json` already requires `webware/webware-log: ^2.0.0-alpha.7`, and that
constraint admits `2.0.0-beta.1`. A targeted
`composer update webware/webware-log` moves the lock with no manifest change.
Verified by dry run: exactly one update, no installs or removals.

### What this removes from the earlier plan

| Earlier proposal | Status |
|---|---|
| commondoc shadows `LoggerInterface` with its own factory | Dropped. `LogFactory` is the only logger factory. |
| `monolog/monolog` added to `require` | Dropped. commondoc uses no Monolog class directly. |
| commondoc builds its own `StreamHandler` instances | Dropped. It maps a config key. |

That is the right outcome: handler composition belongs to the logging component,
and the consumer only states which handler it wants.

### Always on once mapped, and that is fine

Registering the factory means `LogFactory` finds the handler and pushes it, so
logging is on. The earlier `--log` CLI flag is therefore unnecessary: the log is a
**record for review**, and once mapped it is always produced. If an opt-out is
wanted later, `--no-log` is a smaller surface than `--log`, and it is not needed
for the first PR.

### Three things to watch

- **Level.** `Monolog\Handler\StreamHandler` defaults to `DEBUG`, and `logFile`
  carries no level. So every dispatched record is written. Acceptable for a build
  record; a warning-only stderr sink would need a `logLevel` key. Reporting is the
  command's job regardless, so this is a note rather than a gap.
- **Path.** The default `data/log/{channel}.log` is outside the output tree. Keep
  it there. Anything under `--output` is published to Pages.
- **Key style.** `logFile` is camelCase and that is correct: the fleet never uses
  snake_case, because a config key that becomes a variable would then violate the
  PER coding standard. The older siblings in the same array (`log_errors`,
  `process_uuid`, `process_translation`) are the legacy outliers, not `logFile`.
  A consumer writing config by hand should follow `logFile`'s convention and
  expect the snake_case keys to be migrated eventually.

---

**Blocker status: cleared.** `webinertia/webware-log` PR #121 merged
(`2026-11-18T03:45:00Z`), `2.0.0-beta.1` is tagged, and the tag contents were
verified directly rather than assumed. Nothing in Proposal D is blocked.

Until commondoc maps the handler, the command's own output is the only channel,
which is the correct one for reporting.

---

## 11. Page data

The earlier draft deferred this indefinitely because a `Page` needs label,
project name, project slug, url and version label, and a bare directory has no
honest source for them.

The router design changes the answer. A seeded route already carries options, and
`NavigationItem::fromRouteOptions` reads `label`, `parent`, `order` and
`navigation` from exactly those options. So the page a document renders with can
be built from the same route that makes it navigable, rather than from a second
convention:

| `Page` field | Source under the router design |
|---|---|
| `label` | the call site, from front matter or the first heading |
| `url` | `DocumentRoot::published()`, derived rather than accepted |
| `projectName`, `projectSlug`, `versionLabel` | deployment configuration, read once and captured |
| `sourcePath` | the local path |

### How it is built: a factory that returns a factory

`PageFactoryFactory` is a Laminas factory whose service is a **callable**, not a
page object. A page cannot be a container service because there is one per
document and the container builds a service once; what the container can hold is
the thing that makes them.

The shape is not invented here. `Webware\Console\Container\PslTerminalFactoryFactory`
and `Webware\UserManager\Container\UserFactory` both do this for the same reason,
and the user manager is the closer precedent because it also captures
configuration.

The call site supplies only what differs per document:

```php
$page = $pageFactory(localPath: 'guide/setup.md', label: 'Setup');
```

Everything shared is captured by value: the identity from config, and the
`DocumentRoot` the address is derived from. That is the resolution of the dryness
finding: `Page::$url` is no longer an independent parameter anyone can state
wrongly, because the only thing that constructs a page holds the translation.

The service id is the factory's own class name, matching the console precedent, so
there is no loose string key. `DocumentRoot` gains a factory in the same change,
because the callable cannot derive an address without one and nothing else could
construct it from configuration.

Configuration is keyed by the class it configures, `DocumentRoot::class` and
`Page::class`, so a deployment overrides either in its own autoload file. Parsing
uses `Psl\Type` shapes: a section that does not match falls back to defaults rather
than refusing, because a container that cannot build its own services is worse
than a page with a blank project name.

---

## 12. Rejected alternatives

| Alternative | Why rejected |
|---|---|
| `CommonDoc\Build\*` | `Build` does not say whether it is a noun, a verb, local or web. |
| `CommonDoc\Input\MarkdownFiles` | A type is not named for a count it never promises. |
| `CommonDoc\Source\MarkdownSource` | Mixes a provenance noun with a result noun when paired with `Output`. |
| `CommonDoc\DocumentSet` | A plural in spirit: names the container by how many members it may hold. |
| `Output\HtmlPath::for()`, pure suffix swap | Cannot express a base path or a URL style, so it cannot be the single translation. |
| Post-render string replacement for links | Operates on finished HTML, so it can corrupt prose or attribute values. |
| Two key spaces, a route name plus a separate path table | Exactly the desynchronised-identifier defect that produced the code being replaced. |
| A `RouteTable` beside `Mezzio\Router\RouteCollector` | The collector already accumulates and already refuses duplicate names and paths. A table added a third copy of that rule and cached a value `DocumentRoot` computes for free. Built, then deleted; see "Why `RouteTable` was deleted". |
| Seeding the router from a file written by the previous build | Works on the next run, fails on the current one. |
| A wrapper around `FileWriter` for stubbability | Tests already use real temp directories; the wrapper would exist only to be mocked. |
| `file_put_contents` instead of `FileWriter` | Loses atomicity and parent-directory creation. |
| The render cache | Only pays off if rebuilds cost more than correctness risk, and they do not here. |
| commondoc shadowing `LoggerInterface` with its own factory | Unnecessary once webware-log ships a `StreamHandlerFactory`; the handler composition belongs to the logging component. |
| Adding `monolog/monolog` to `require` | No Monolog class is used directly once the factory is mapped in config. |

---

## 13. Verification plan

- `mago format`, `lint`, `analyze`, `guard` exit 0, no new baseline entry.
- Unit and integration suites green.
- `#[CoversClass]` and `#[CoversMethod]` on every new test class.
- `createStub()` where a double only returns values.
- An integration test over a real temp tree: nested directories, an `index.md`
  at the root and one level down, an asset, and a relative link between two
  documents. Assert files exist at the translated paths, links point at published
  paths, and output uses LF endings.
- Negative tests: unreadable directory refused; a link to a missing document
  fails the build; `guide/setup.md` plus `guide/setup/index.md` fails as a
  collision; a path escaping the root refused.

---

## 14. File manifest

Every file this work creates or touches. Names follow the settled decisions:
singular nouns, no `\Build`, no snake_case, no `Helper`/`Manager`/`Service`
suffix. `R` means reviewed and agreed in this record; `?` means the name is still
one of the open questions.

### Status

Built as skeletons in commit `skeleton: add the build types the design needs`.
`DocumentRoot` is implemented and tested.

| File | State | Tests |
|---|---|---|
| `src/Input/Tree.php` | **implemented** | `TreeTest`, 9 tests, on `Psl\Filesystem` |
| `src/Input/Markdown.php` | **implemented**, pure filter over a path list | `MarkdownTest`, 7 tests |
| `src/Input/Asset.php` | **implemented**, difference against the documents | `AssetTest`, 6 tests |
| `src/Output/PathType.php` | complete (enum) | covered by `DocumentRootTest` |
| `src/Output/DocumentRoot.php` | **implemented** | `DocumentRootTest`, 18 tests, 100% lines and methods |
| `src/Output/LinkRewriter.php` | **implemented**, listener over parsed links and images | `LinkRewriterTest`, 13 tests, 100% lines and methods |
| `src/Record/BuildRecord.php` | **implemented** | `BuildRecordTest`, 11 tests |
| `src/Http/Middleware/NotServedMiddleware.php` | **implemented** | `NotServedMiddlewareTest`, 2 tests |

`src/Router/RouteTable.php` was here and has been **deleted**; see "Why
`RouteTable` was deleted".

### Why the scans are built on PSL

`Psl\Filesystem` is the same library `FileWriter` writes through, so one
dependency describes both ends of the filesystem work rather than a native
iterator here and PSL there. The typing is the second reason: `read_directory()`
returns a `list<non-empty-string>` and throws typed refusals, so the analyzer
accepted a declared `@throws` list instead of an untyped throw, and a caller
catching `InvalidArgumentException` still catches all three because PSL's
exceptions extend it.

`Tree` exists so a document scan and an asset scan cannot disagree about what
exists or how a path is spelled. Both are then one filter each over the list it
returns.

### One traversal, then partition

The filters take the path list rather than a root, and that is the correction
that came out of reviewing the first version.

The first version had `Markdown::scan(string $root)` and
`Asset::scan(string $root)`, each calling `Tree::at($root)` itself. That walked
the tree once per type, so a build using both walked it twice. Expressing `Asset`
as a difference against `Markdown::scan($root)` made it three walks, which is
worse in the name of less duplication.

Traversal is the expensive part: one `read_directory()` per directory regardless
of file count. A suffix test is a string comparison. So the count of filters is
irrelevant and the count of traversals is everything, and the fix is for the
caller to walk once:

```php
$paths     = Tree::at($root)->paths();
$documents = (new Markdown())->scan($paths);
$assets    = (new Asset())->scan(paths: $paths, documents: $documents);
```

Three things follow. The filters become pure functions of a list, so they need no
directory, no refusals and no temporary tree to test. Root validation lives in
`Tree::at()` only, instead of being restated by every type. And adding a type
costs one pass over an array rather than another pass over the filesystem.

**Globbing was considered and rejected for this job.** A single `glob()` per
directory does return every match, and it sorts by default. But it returns
directories that match the pattern, it is case-sensitive even on a
case-insensitive filesystem, and it is `opendir`/`readdir` internally, so it does
not reduce traversal when the complete entry list is still needed for assets.
Glob becomes the right tool only if assets stop requiring a walk.

**Generators were rejected.** `Tree::paths()` sorts by byte value so two builds
agree, and a sort needs the whole set before the first element can be emitted. A
generator would add a type and lose the ordering guarantee at the same time.

**`scan` is the wrong verb for these two methods.** Scanning is the walking, and
the walking now lives in `Tree`. What remains is selecting from a list. The name
is unchanged because it is the owner's decision, not mine.

Two decisions worth recording:

- **`finfo` was dropped.** It was in the earlier draft because fileinfo was
  requested, but `Filesystem\is_file()` already restricts the walk to regular
  files, so the MIME probe decided nothing that the walk had not decided. An
  unreadable asset is deliberately left in the list rather than filtered out: it
  fails loudly when copied, where a silent omission would surface as a broken
  image with no diagnostic.
- **Symlinked directories are not followed.** A link cycle would otherwise make
  a scan run forever, and the guard costs one call. Symlinked files are still
  published.

The `.md` extension is matched case-insensitively. A `.MD` document is a
document, and skipping it silently would publish a site missing a page.

### What `DocumentRoot` settled while being implemented

The two answers are asymmetric on purpose. `published()` carries the base path
and `file()` does not, because the output directory is mounted *at* the base
path rather than containing it. A single method returning a pair would have
hidden that, and a file that carries the prefix writes into the wrong directory.

A document named `index` is the index of its own directory. `guide/index.md`
publishes at `/guide/` and is served by `guide/index.html`, not
`/guide/index/`. That one rule is what keeps an address and the file serving it
in agreement, and it is the same rule for both path types.

`index.md` at the root publishes at the base path itself, which is why a
repository subpath still produces something at `/repo/`.

Infection caught a real defect here that the tests did not. The first version
guarded the file path type with `'' === $relative`, which is unreachable:
`relativeOf('index.md')` yields `index`, never an empty string. Nine mutants
survived in that branch because no input could reach it, and the fix was to
delete the dead ternary rather than to add a test for it. That is worth
remembering when the other types are filled in: a mutant that survives because a
branch cannot execute is a code defect, not a missing test.

The file path type also gives `guide/index.md` the path `/guide/index.html`,
which is correct for that type, since a file path is what it produces.

### Why `RouteTable` was deleted

`RouteTable` is gone, along with its test, and the reason matters more than the
deletion.

It duplicated `Mezzio\Router\RouteCollector`. Read side by side, the collector
already accumulates (`$this->routes[] = $route`, then `$this->router->addRoute()`)
and its `DuplicateRouteDetector` refuses both a duplicate name and a duplicate
path, raising `DuplicateRouteException`. That is the whole of `RouteTable`'s
storage and collision logic, built and tested upstream.

It was also the **third** copy of that rule in the fleet, after
`DuplicateRouteDetector` and `webware/query-param-router`'s
`QueryParamDuplicateRouteDetector`.

The one capability the collector lacks is O(1) name-to-path lookup, and that was
not justification either: `DocumentRoot::published()` is a pure function of the
local path, so a table would cache a value cheaper to compute than to maintain.
The only question recomputation cannot answer is whether a link target exists,
and `Markdown::scan()` already answers it.

Why the mistake happened is worth recording. The plan said the container holds a
collector rather than a frozen route set, which is correct; the code then
contradicted it, because the collector's `route()` was never read before the
table was designed. The lesson is the same one the audit was hunting for: read
the dependency's implementation before adding a type beside it.

Two consequences:

- **`RouteProviderInterface` is no longer used.** It exists so a config-driven
  provider can hand routes to a collector, and a build is not one. The translate
  pass calls `$collector->get(path: ..., middleware: ..., name: ...)` directly.
- **Collisions now raise `DuplicateRouteException`,** not the
  `InvalidArgumentException` the table used. One vocabulary rather than two, and
  the upstream one now actually fires: previously the table's check ran first and
  pre-empted it.

`NotServedMiddleware` survives and gains its own test, because a route still
cannot exist without middleware and a test file that covered it as a side effect
of covering the table is not the same thing.

### Why `NotServedMiddleware` lives under `Http`

A Mezzio route cannot exist without middleware, and a build never dispatches one,
so the route carries a type whose only honest behaviour is to refuse. It throws
rather than returning a response, because reaching it means a build route was
mounted somewhere that serves requests, and a silent success would hide that.

The first version lived in `CommonDoc\Router\` and failed `mago guard`:
PSR-15 is permitted only under `**\Http\**` (plus the async and router
exceptions), and the class also had to be named `*Middleware`. Moving the file
satisfies the boundary instead of widening it, which is the correct direction:
weakening a fleet rule to admit one class would weaken it for every package. The
boundary is also saying something true here, because a documentation builder that
starts accepting requests has stopped being a documentation builder.

### Create

| File | Type | Signature or role | Name |
|---|---|---|---|
| `src/Input/Markdown.php` | class | `scan(array $paths): list<string>` | R |
| `src/Input/Asset.php` | class | `scan(array $paths, array $documents): list<string>` | R |
| `src/Input/Tree.php` | class | `paths(): list<non-empty-string>` | R |
| `src/Output/PathType.php` | enum | `case Directory; case File;` | R |
| `src/Output/DocumentRoot.php` | readonly class | `published()` and `file()` | R |
| `src/Output/LinkRewriter.php` | readonly class | CommonMark `DocumentParsedEvent` listener | R |
| `src/Record/BuildRecord.php` | readonly class | `input`, `output`, `failed` | R |
| `src/Router/RouteTable.php` | **deleted** | duplicated the Mezzio collector; see "Why `RouteTable` was deleted" | n/a |
| `src/Http/Middleware/NotServedMiddleware.php` | readonly class | PSR-15 `process()` that refuses; the only middleware a build route can carry | R |

### Modify

| File | Change |
|---|---|
| `src/CommandHandler/BuildSiteHandler.php` | becomes the coordinator over the ordered passes |
| `src/Container/BuildSiteHandlerFactory.php` | inject the converter, the scan, the translation, the route table |
| `src/Console/BuildCommand.php` | narrow `result` to `BuildRecord` and print written and failed |
| `src/ConfigProvider.php` | register `StreamHandlerFactory`; register the new services |
| `src/Container/EnvironmentFactory.php` | **done**: registers the page listener on the parsed event |
| `src/View/PageContextListener.php` | **done**: writes on the parsed event, so the path a link resolves against is on the document before a rewrite |
| `config/config.php` | aggregate `Webware\Log\ConfigProvider` |
| `composer.json` | add `webware/webware-filewriter` to `require` |

### Create, tests

Mirrors `src/`. One class per subject, `#\(CoversClass\)` plus `#\(CoversMethod\)`.

`test/unit/Input/MarkdownTest.php`, `test/unit/Input/AssetTest.php`,
`test/unit/Output/DocumentRootTest.php`, `test/unit/Output/LinkRewriterTest.php`,
`test/unit/Record/BuildRecordTest.php`,
`test/unit/Http/Middleware/NotServedMiddlewareTest.php`,
`test/unit/CommandHandler/BuildSiteHandlerTest.php`,
`test/unit/Console/BuildCommandTest.php`.

`PathType` has no test file of its own: it is a closed set with no behaviour, so
`DocumentRootTest` covers it through the two constructor values rather than
restating the cases.

Plus one integration test over a real temp tree, which is where the whole design
is actually proven: `test/integration/BuildTest.php`.

### Shapes, as implemented

```php
namespace CommonDoc\Input;

final readonly class Markdown
{
    public const string EXTENSION = '.md';

    /** @param list<non-empty-string> $paths */
    public function scan(array $paths): array;
}

namespace CommonDoc\Input;

final readonly class Asset
{
    /**
     * @param list<non-empty-string> $paths
     * @param list<non-empty-string> $documents
     */
    public function scan(array $paths, array $documents): array;
}

namespace CommonDoc\Output;

enum PathType: string
{
    case Directory = 'directory';
    case File      = 'file';
}

namespace CommonDoc\Output;

final readonly class DocumentRoot
{
    public function __construct(
        public string $basePath,
        public PathType $pathType = PathType::Directory,
    ) {}

    /** '/guide/setup/' or '/guide/setup.html' */
    public function published(string $localPath): string;

    /** 'guide/setup/index.html' or 'guide/setup.html' */
    public function file(string $localPath): string;

    /** '/images/diagram.png', the same in either path type */
    public function asset(string $localPath): string;
}

namespace CommonDoc\Output;

final readonly class LinkRewriter
{
    /**
     * @param list<non-empty-string> $documents
     * @param list<non-empty-string> $assets
     */
    public function __construct(
        DocumentRoot $root,
        array $documents,
        array $assets,
    ) {}

    /** @throws RuntimeException when a target names nothing the scan found */
    public function __invoke(DocumentParsedEvent $event): void;
}

namespace CommonDoc\Record;

final readonly class BuildRecord
{
    /**
     * @param list<non-empty-string> $input
     * @param list<non-empty-string> $output
     * @param array<non-empty-string, non-empty-string> $failed
     */
    public function __construct(
        public array $input,
        public array $output,
        public array $failed,
    ) {}
}
```

### What a skeleton contains

Signature, docblock with the `@throws` the body will have, `final readonly`, and
a body that throws `LogicException('Not implemented.')`. Not an empty body: an
empty body that returns `[]` is exactly how `BuildSiteHandler` became a
successful no-op, and a skeleton should fail loudly rather than quietly.

Skeletons are useful only where the signature is the thing under review. Here
they also let `BuildCommand` and `BuildSiteHandlerFactory` be wired and the
integration test written against a fixed shape, which is why I am proposing them
as one commit rather than filling each class in turn.

---

## 15. Open questions for the owner

1. The translation type name: `DocumentRoot`, `Deployment`, or something else.
2. Directory path type versus file path type, per section 7.
3. Name for the asset scan, alongside `Input\Markdown`.
4. Where the deployment configuration lives: `config/autoload`, command options,
   or the site config file.
5. Whether `planning/` is tracked, and on which branch.
6. Whether `logFile` should be left at the webware-log default
   (`data/log/{channel}.log`) or stated explicitly in commondoc's config.
7. Whether a link target naming a directory (`../guide/`) should resolve to that
   directory's index document. It is refused today because the guess is wrong for
   any directory without one.

---

## 16. Orchestration, as implemented

The coordinator and the console narrowing landed after the link rewriter, in
their own pull request, which is the split the owner asked for.

### One environment and one converter per build

The owner's decision, stated twice: "I would expect an Environment and converter
per build honestly." The reason it matters here rather than being tidiness is
that `Environment` is a shared service and `addEventListener` has no freeze
guard, so a build that registered its link rewriter on the shared environment
would stack a listener per build, each bound to a stale document list. That is
also what would make running each repository's build in its own process a
correctness requirement rather than an option.

The mechanism is `build()`, not a callable factory. `ServiceManager::build()`
never caches, so a fresh environment and a fresh page each time are one method
call apart, and `MarkdownConverterFactory` needs no second type to own the
difference. It was a `ConverterFactoryFactory` returning a callable first, and
that was removed: a factory factory is justified only when the instance must be
constructed at a call site that cannot reach the container. Here the call site
can, so the callable was a type earning nothing.

`MarkdownConverterFactory` therefore has two modes, told apart by its options:

| Request | Result |
|---|---|
| no options, or options that are not a scan | a converter over the shared environment |
| `['documents' => ..., 'assets' => ...]` | a converter over a freshly built environment carrying a `LinkRewriter` bound to those lists |

The fresh environment comes from `$locator->build(EnvironmentInterface::class)`,
so the extension list and the page listener are exactly what the shared one has.
Only the rewriter differs.

`Page` works the same way. It is one per document, so it cannot be a shared
service, and the caller supplies only what differs per document:

```php
$services->build(Page::class, ['localPath' => $document, 'label' => $label]);
```

`PageFactory` reads that section of config and derives the address, so there is
still exactly one translation. What is gone is `PageFactoryFactory` and the
callable it returned.

`BuildSiteHandler` therefore receives a `ServiceLocatorInterface` and uses it
only for `build()`. Every fixed collaborator is still injected as an instance,
and the locator is not used to resolve one.

### Why `EnvironmentFactory` is not registered under its own name

It was tried, and the integration suite refused it: `$container->get(
EnvironmentFactory::class)` returns a `League\CommonMark\Environment\Environment`,
because the service map invokes the factory rather than returning it. So the
typed refusal was correct, and the entry was removed. Nothing needs it: the
handler builds `EnvironmentInterface`, which is registered.

### What the handler does

| Pass | Behaviour |
|---|---|
| scan | `Tree::at()->paths()`, then `Markdown::scan()` and `Asset::scan()` |
| seed | one `RouteCollector::get()` per document: name is the local path, path is the published path |
| render and write | per document: set the page, convert, clear the page in a `finally`, write through `FileWriter::writeTree()` |
| copy | per asset: read and write at its own local path |
| report | one `BuildRecord`, and a status read from `record->complete()` |

Seeding runs to completion before the first document is rendered, because a link
may target a document the loop has not reached. Collision detection is
`DuplicateRouteDetector`'s, exercised by a test with `guide/setup.md` and
`guide/setup/index.md`, which both publish at `/guide/setup/`.

### Two failure policies, stated rather than implied

A document that fails is recorded and the build continues: the operator wants the
whole list rather than the first problem, and `BuildRecord` is exactly the shape
for that.

An asset that fails to copy throws, because `BuildRecord` describes documents and
has an invariant tying its three lists to them. Recording an asset alongside them
would break that invariant, and inventing a second list would change a merged
type. **This is the one place the design is incomplete**: a site with one
unreadable asset fails without saying which asset, and the answer is either to
extend `BuildRecord` or to accept the abort. It is recorded as an open question
rather than papered over.

### The page label is derived, and that is temporary

`PageFactoryFactory` takes a label, and nothing in the build produces one. The
label is the file name without its document suffix, with no special case for
`index`, so `guide/setup.md` is `setup` and `index.md` is `index`. Deriving
something mechanical was chosen over inventing navigation vocabulary in the
coordinator; a real label needs a source this build does not have, which is the
document's first heading or a front-matter field.

### `NotServedMiddleware` is an invokable

A route cannot exist without middleware and this one has nothing to inject, so it
is declared under `invokables` rather than behind a factory that would only
return `new`. `Dependencies` in the provider's type aliases gained the key.

`webware/webware-filewriter` moved from a transitive dependency to a direct
requirement, because `src/` now names `FileWriter`.

### What the console prints now

`BuildCommand` narrows the result to a `BuildRecord` and prints each written
document and each failure reason. A result that is not a record is **said out
loud** and exits non-zero, which is the specific defect this replaces: the old
version filtered the result for strings, so an empty result printed nothing and
a silent no-op was indistinguishable from a successful build.

The exit code follows the record rather than the status. The handler derives the
status from the record, so the two agree; following the record means a future
handler bug that reported success beside a failure would still exit non-zero.

### Verified

| Gate | Result |
|---|---|
| `mago format`, `lint`, `analyze`, `guard` | 0 |
| unit | 180 tests, 317 assertions, 1 skipped |
| integration | 23 tests, 135 assertions |
| Infection | 341 mutants, 0 escaped, MSI 100% |
| coverage | `BuildSiteHandler` 67/67 lines, `PageFactory` 35/35, `MarkdownConverterFactory` 19/19 |

The integration suite dispatches the real command through the container's own
bus into the handler and asserts a file exists at the translated path, so the
wiring is tested end to end rather than by type assertions.