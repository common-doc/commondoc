# Quickstart: Validating Document View Rendering

**Feature**: [spec.md](./spec.md)

**Plan**: [plan.md](./plan.md)

This guide validates the feature end to end. It assumes a working checkout with dependencies
installed.

## Prerequisites

```powershell
composer install
```

- PHP per `composer.json` (`~8.4.1 || ~8.5.0 || ~8.6.0`), platform pinned to `8.4.99`.
- `league/commonmark`, `mezzio/mezzio-laminasviewrenderer`, `laminas/laminas-view` and
  `webware/webware-theme` present in the vendor tree.
- `vendor/bin/mago`, `vendor/bin/phpunit` and `vendor/bin/infection` available.

## Scenario 1: The document body renders through a real view

**Goal**: prove the seam produces view-rendered output rather than wrapped HTML.

1. Build the environment with `EnvironmentFactory`.
2. Convert a short Markdown document, for example a heading and a paragraph.
3. Read the converted output.

**Expected**: the output contains the configured layout markers as well as the converted heading and
paragraph text. The heading and paragraph text is nested inside the layout, proving the body is a
rendered template rather than a string concatenated onto it.

## Scenario 2: A view helper works inside the document body

**Goal**: the reason the feature exists.

1. Configure the body template so it invokes a Laminas view helper, for example a title helper or a
   placeholder.
2. Render one document whose page labels the current section.
3. Inspect the output.

**Expected**: the helper's rendered output appears inside the body. No exception is raised about
helpers being unavailable, which is what a finished-HTML wrap produces.

**Fail signal**: an empty helper result, or output that contains a raw helper fragment, indicates the
render did not run inside a real view.

## Scenario 3: A site overrides the body template by configuration alone

**Goal**: the hard requirement that override needs zero code change.

1. Take the register wiring test container.
2. Add a site configuration entry in `templates.map` for `commondoc::document` pointing at a
   temporary body template that emits a marker string.
3. Render a document without touching any class in this package.

**Expected**: the marker string appears, and no class was changed. When the site entry is removed,
the packaged body renders again.

**Fail signal**: the packaged template wins over the site entry, or the override requires a
delegator, an event listener or a class change.

## Scenario 4: A render with no page succeeds

**Goal**: a conversion outside a build must not fail.

1. Build the environment.
2. Convert a document without ever calling `PageContext::set()`.

**Expected**: a rendered string. The body renders, the layout wraps it, and nothing throws.

## Scenario 5: Many documents in one process stay independent

**Goal**: sibling renders do not leak per-render state.

1. Build one environment and one converter.
2. Convert ten distinct documents, each with a different heading and a different page, through a
   body template that uses a placeholder helper.
3. Compare each output against its own document.

**Expected**: every output contains only its own content. No document's placeholder or title content
appears in another document's output.

## Scenario 6: The retired middleware is gone

**Goal**: no dead path remains.

1. Inspect the middleware pipeline configuration.
2. Search the source and test trees for the retired middleware and its factory.

**Expected**: neither the pipeline nor the factories map names the retired middleware, and neither
the class, its factory nor its tests exist in the tree.

## Gates

Every command must exit zero, and no new baseline entry may appear for new code.

```powershell
vendor/bin/mago format
vendor/bin/mago lint
vendor/bin/mago analyze
vendor/bin/mago guard
composer test
composer test-integration
composer test-coverage
vendor/bin/infection
```

- Mutation testing must meet `min_msi` and `min_covered_msi` of 95.
- Coverage metadata is required: every test class carries `#[CoversClass]` and a `#[CoversMethod]`
  per exercised method.
- Unit tests use `createStub()` for value doubles and `createMock()` only where `expects()` is
  configured.

## Definition of done

- Scenarios 1 to 6 pass.
- Every gate above passes.
- `git diff` against the feature branch base contains no source, test, configuration or template
  change that this plan did not list.
