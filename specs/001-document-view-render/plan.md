# Implementation Plan: Document View Rendering

**Branch**: `tyrsson-document-render-speckit` | **Date**: 2026-11-12 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/001-document-view-render/spec.md`

## Summary

Replace the finished-HTML wrapping middleware with a real view render for Markdown documents.
`common-doc/commondoc` registers `CommonDoc\View\DocumentViewRenderer` as a CommonMark node renderer
for `League\CommonMark\Node\Block\Document` at priority 10. The renderer asks CommonMark to render
the document's children into a body string, then renders that body through `commondoc::document`
with `documentBody`, and lets `LaminasViewRenderer` wrap it in `commondoc::layout`. Every Laminas
view helper therefore runs inside a real view for every node, and a site can replace
`commondoc::document` through `templates.map` configuration alone.

The page travels into the render without widening `ConverterInterface`: `PageContext` holds the
current page for one conversion, `PageContextListener` copies it into the document's data on
`DocumentPreRenderEvent`, and the renderer reads it back. A page is a `final readonly` view model,
never a render root. `HtmlRenderer`, `MarkdownConverter` and `MarkdownConverterInterface` are
untouched.

## Technical Context

**Language/Version**: PHP platform pinned `8.4.99`; package supports `~8.4.1 || ~8.5.0 || ~8.6.0`

**Primary Dependencies**: `league/commonmark` ^2.10, `mezzio/mezzio-laminasviewrenderer` ^3.0
(promoted to a direct requirement), `mezzio/mezzio-template` (via the renderer, for
`TemplateRendererInterface`), `laminas/laminas-view` ^3.1, `webware/webware-theme` ^1.0.0-alpha.5
(template resolver precedence)

**Storage**: Filesystem templates only; no database, no cache

**Testing**: PHPUnit ^13.4.4 with attributes and coverage metadata; Infection ^0.35.0 at
`min_msi` and `min_covered_msi` 95; `mago format`, `mago lint`, `mago analyze`, `mago guard`

**Target Platform**: PHP CLI on the CI matrix (PHP 8.4 and 8.5) and developer Windows or Unix

**Project Type**: library, single Composer package, PSR-4 `CommonDoc\` to `src/`

**Performance Goals**: one view render per document; template resolution by map key rather than a
path stack scan; one long-lived renderer per process; no per-render renderer construction

**Constraints**: no em dash or en dash in any artifact; LF line endings only; mago exits zero with
no new baseline entry for new code; `#[CoversClass]` and `#[CoversMethod]` on every test class;
`createStub()` for value doubles

**Scale/Scope**: 7 new source files, 1 new template, 8 new tests, 4 deleted files, 5 modified files

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Code Quality

| Requirement | Status | Evidence |
|---|---|---|
| Strict types and declared types everywhere | PASS | Every new class declares return types, including `static` on the mutators that only throw |
| Readonly value objects | PASS | `Page` and `PageContext` carry no mutable state; `Page` is `final readonly` |
| No magic accessors | PASS | `Page` implements the view model contract explicitly; no `__get`, `__set` or `__call` |
| No dead code | PASS | The wrapping middleware, its factory and its tests are deleted rather than left as a pass-through |
| Static analysis clean | PASS | `mago analyze` must exit zero with no new baseline entry; the injection seam is typed against `TemplateRendererInterface` precisely so analysis can check it |
| No analyzer-driven reshaping | PASS | Types are declared from the design; if an analyzer objects, the declaration is corrected, the structure is not |

### II. Testing Standards

| Requirement | Status | Evidence |
|---|---|---|
| Behaviour tested, not implementation | PASS | Tests read rendered output and thrown exceptions, not internal call counts |
| Coverage metadata | PASS | Every new test class carries `#[CoversClass]` plus `#[CoversMethod]` per exercised method |
| `#[Test]` attribute and `static::assert*` | PASS | House style for all new tests |
| Mock discipline | PASS | `createStub()` for the template renderer, child renderer and document doubles; `createMock()` only where `expects()` is configured |
| Contract tests are unit tests | PASS | The `ModelInterface` conformance test lives in `test/unit`; only the real container render lives in `test/integration` |
| Mutation testing | PASS | New code is written so each branch is observable: page present versus absent, correct node versus wrong node, each mutator throwing |

### III. UX Consistency

| Requirement | Status | Evidence |
|---|---|---|
| Configuration-only override | PASS | `templates.map` entry plus the documented precedence theme 100, map 50, path stack 1 |
| Actionable errors | PASS | Each mutator's `LogicException` names immutability and the page label; a missing template surfaces the renderer's template-not-found exception, which names the template |
| Consistent public naming | PASS | `PageContext`, not `PageContextHolder`; no `Holder`, `Manager`, `Helper`, `Util` or `Service` suffix; singular names; the listener carries `Listener` |
| Predictable packaging | PASS | `ConfigProvider` contributes the view configuration, so a consumer needs configuration only |

### IV. Performance

| Requirement | Status | Evidence |
|---|---|---|
| One render per document | PASS | The seam performs exactly one `TemplateRendererInterface::render()` call |
| Cheapest resolver that satisfies the requirement | PASS | Map entries instead of a path stack; the layout is named once in `templates.layout` |
| No redundant work | PASS | CommonMark renders the children once; the seam does not re-render or post-process |
| No added caching | PASS | Helper reset is the library's job; caching it here would duplicate and drift |
| Shared renderer | PASS | One renderer per process; `render()` resets helper state after every render |

### Quality Gates table

| Gate | Command | Threshold |
|---|---|---|
| Format | `vendor/bin/mago format` | Exit zero |
| Lint | `vendor/bin/mago lint` | Exit zero, no new baseline entry |
| Static analysis | `vendor/bin/mago analyze` | Exit zero, no new baseline entry |
| Guard | `vendor/bin/mago guard` | Exit zero |
| Unit tests | `composer test` | All pass |
| Integration tests | `composer test-integration` | All pass |
| Coverage | `composer test-coverage` | Every new class covered |
| Mutation | `vendor/bin/infection` | `min_msi` and `min_covered_msi` 95 |

**Result**: PASS. No gate requires a justified deviation.

## Project Structure

### Documentation (this feature)

```text
specs/001-document-view-render/
+-- spec.md
+-- plan.md
+-- research.md
+-- data-model.md
+-- quickstart.md
+-- contracts/
|   +-- document-renderer.md
|   +-- page-context.md
|   +-- page-view-model.md
|   +-- template-registry.md
+-- checklists/
|   +-- requirements.md
+-- tasks.md
```

### Source Code (repository root)

```text
src/
+-- ConfigProvider.php                       # modified: view config, pipeline, factories
+-- Command/
|   +-- BuildSiteCommand.php                 # unchanged
+-- CommandHandler/
|   +-- BuildSiteHandler.php                 # unchanged (stub; no conversion yet)
+-- Console/
|   +-- BuildCommand.php                     # unchanged
+-- Container/
|   +-- DocumentViewRendererFactory.php      # new
|   +-- EnvironmentFactory.php               # modified: register renderer and listener
|   +-- PageContextFactory.php               # new
|   +-- PageContextListenerFactory.php       # new
+-- Listener/
|   +-- CommandPostHandleListener.php        # unchanged
|   +-- CommandPreHandleListener.php         # unchanged
+-- Middleware/
|   +-- SourceLoadingMiddleware.php          # unchanged
+-- View/
    +-- DocumentViewRenderer.php             # new
    +-- Page.php                             # new
    +-- PageContext.php                      # new
    +-- PageContextListener.php              # new

templates/commondoc/
+-- document.phtml                           # modified: body template, uses documentBody
+-- layout.phtml                             # new: layout shell, renders content

test/unit/
+-- ConfigProviderTest.php                   # modified
+-- DocumentViewRendererFactoryTest.php      # new
+-- DocumentViewRendererTest.php             # new
+-- EnvironmentFactoryTest.php               # modified
+-- PageContextFactoryTest.php               # new
+-- PageContextListenerFactoryTest.php       # new
+-- PageContextListenerTest.php              # new
+-- PageContextTest.php                      # new
+-- PageTest.php                             # new

test/integration/
+-- ConfigProviderWiringTest.php             # modified
+-- DocumentViewRenderingTest.php            # new

deleted:
src/Container/LayoutWrappingMiddlewareFactory.php
src/Middleware/LayoutWrappingMiddleware.php
test/unit/LayoutWrappingMiddlewareFactoryTest.php
test/unit/LayoutWrappingMiddlewareTest.php

composer.json                                # modified: direct requirement, php constraint
```

**Structure Decision**: Single package, the layout the repository already uses. New production
types go under `CommonDoc\View`, because they model and render a document view rather than being
container plumbing. Factories stay in `CommonDoc\Container`, matching every existing factory, so the
`ConfigProvider` factories map gains three entries and loses one. Tests stay flat in `test/unit`
under the `CommonDocTest` namespace, matching every existing unit test, so no test directory is
introduced.

## Phase 0 Research Outcomes

See [research.md](./research.md). Eleven decisions, no unresolved clarification. The three that
shape the code most:

- D3: the seam is typed against `TemplateRendererInterface` because `LaminasViewRenderer` is `final`
  in 3.0.0 and cannot be doubled.
- D6: a page is never attached to a rendered model tree, because `renderRecursively()` calls
  `beforeRender()` on children, which writes variables and template onto the child.
- D7: `PageContext` plus `DocumentPreRenderEvent` plus document data is the only viable transport,
  because `ConverterInterface::convert(string)` carries no context.

## Phase 1 Design Outcomes

See [data-model.md](./data-model.md), [contracts/](./contracts) and [quickstart.md](./quickstart.md).

### Wiring the plan settles

`ConfigProvider::getViewConfig()` returns exactly:

```php
[
    'templates' => [
        'map' => [
            'commondoc::document' => __DIR__ . '/../templates/commondoc/document.phtml',
            'commondoc::layout' => __DIR__ . '/../templates/commondoc/layout.phtml',
        ],
        'layout' => 'commondoc::layout',
    ],
]
```

`ConfigProvider::getDependencies()` gains factories for `DocumentViewRenderer`, `PageContext` and
`PageContextListener`, and loses the factory entry for the deleted middleware.

`EnvironmentFactory` resolves `DocumentViewRenderer` and `PageContextListener` from the container and
registers them on the environment it returns:

```php
$environment->addRenderer(Document::class, $viewRenderer, 10);
$environment->addEventListener(DocumentPreRenderEvent::class, $pageContextListener);
```

Registrations happen before the environment is returned and therefore before the environment is
used, so no `AlreadyInitializedException` path is introduced.

### The conversion bracket, and where it does not yet exist

`PageContext` is only useful if the caller brackets each conversion with `set()` and a `finally`
`clear()`. No production code performs a conversion today: `BuildSiteHandler::handle()` returns an
empty success result and never touches `ConverterInterface`. This plan therefore does not invent a
build loop. Instead:

- the bracket obligation is a documented contract
  ([contracts/page-context.md](./contracts/page-context.md));
- `test/integration/DocumentViewRenderingTest.php` performs the bracket exactly as a caller must, so
  the protocol is proven end to end against the real container;
- the renderer tolerates an absent page, so a conversion made today by any consumer, bracketed or
  not, cannot fail.

### Decisions taken beyond the settled requirements

| Decision | Choice | Why |
|---|---|---|
| Namespace for new types | `CommonDoc\View` | Does not repeat the parent segment; groups the page model with the render that consumes it |
| `mezzio/mezzio-laminasviewrenderer` | Promoted to a direct `require` at `^3.0` | The package now imports `TemplateRendererInterface` and depends on 3.0.0 finality and per-render reset |
| PHP constraint | `~8.4.1 \|\| ~8.5.0 \|\| ~8.6.0` | Matches the stated support window; platform pin stays `8.4.99` |
| CI matrix | Unchanged (8.4 and 8.5) | Adding 8.6 is a CI policy change, outside this feature |
| `commondoc::page` | Default template name on `Page`, not registered in the map | Nothing renders a page directly in v1; registering a template that does not exist would be a lie |
| Container scope of `PageContext` | Shared service, one slot per process | The caller and the listener must observe the same instance |
| `ConfigProvider` pipeline docblock | Rewritten without the removed priority | The class docblock currently lists a priority that no longer exists |
| Factory docblock claiming load bearing | Deleted with the factory | The claim only ever described the middleware's pipeline slot |

## Complexity Tracking

No constitution violations. One owner-approved removal is recorded here for review visibility.

| Change | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Delete `LayoutWrappingMiddleware`, its factory and its tests | It cannot see the view layer, so it can never produce helper output, and it holds a pipeline position that no longer means anything | Leaving it as a pass-through keeps dead code on the render path and misleads the next reader about where document output is produced. Owner approved the removal. |
