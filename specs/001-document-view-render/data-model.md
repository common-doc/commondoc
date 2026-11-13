# Phase 1 Data Model: Document View Rendering

**Feature**: [spec.md](./spec.md)

**Contracts**: [contracts/page-view-model.md](./contracts/page-view-model.md),
[contracts/document-renderer.md](./contracts/document-renderer.md),
[contracts/page-context.md](./contracts/page-context.md),
[contracts/template-registry.md](./contracts/template-registry.md)

## Entity: Page

A converted document's identity, as a readonly view model. One page exists per document being
rendered, supplied by the caller that owns the site model.

**Namespace**: `CommonDoc\View`

| Field | Type | Default | Invariant |
|---|---|---|---|
| `label` | `string` | none, required | Non-empty display label |
| `url` | `string` | none, required | Non-empty site-relative URL |
| `projectSlug` | `string` | none, required | Non-empty |
| `projectName` | `string` | none, required | Non-empty |
| `versionLabel` | `string` | none, required | Non-empty |
| `sourcePath` | `string` | none, required | Non-empty |
| `template` | `string` | `commondoc::page` | Non-empty template name |
| `captureTo` | `string` | `page` | Non-empty capture name |
| `append` | `bool` | `false` | none |
| `terminal` | `bool` | `false` | none |
| `children` | `list<Page>` | `[]` | Sequential integer keys starting at zero, every value a `Page` |

**Construction**: named constructor arguments only. Ordering of arguments is alphabetical by
field name in the signature so named-argument call sites read consistently.

**Relationship**: `Page` owns `list<Page>`. The tree is built by the caller; the class enforces that
it is never mutated afterwards.

### Read interface (implemented)

| Method | Returns |
|---|---|
| `getTemplate()` | `$this->template` |
| `getVariables()` | the typed projection below |
| `getVariable($name, $default = null)` | the named entry of the projection, or `$default` |
| `getChildren()` | `$this->children` |
| `hasChildren()` | `$this->children !== []` |
| `captureTo()` | `$this->captureTo` |
| `isAppend()` | `$this->append` |
| `terminate()` | `$this->terminal` |
| `count()` | `count($this->children)` |
| `getIterator()` | `ArrayIterator` over `$this->children` |

The typed projection, keyed by field name: `label`, `url`, `projectSlug`, `projectName`,
`versionLabel`, `sourcePath`, `captureTo`, `append`, `terminal`, `children`. No key is ever named
`content`.

### Mutation interface (must throw)

| Method | Throws |
|---|---|
| `setVariable($name, $value)` | `LogicException` naming immutability and the page label |
| `setVariables($variables, $overwrite = false)` | `LogicException` naming immutability |
| `setTemplate($template)` | `LogicException` naming immutability |
| `addChild($child, $captureTo = null, $append = null)` | `LogicException` naming immutability |
| `setCaptureTo($captureTo)` | `LogicException` naming immutability |
| `setTerminal($terminal)` | `LogicException` naming immutability |
| `setAppend($append)` | `LogicException` naming immutability |

The declared return type on each mutator is `static`, so the class satisfies the contract's shape
without ever returning.

### State transitions

None. A `Page` is created complete and never changes. The only observable transition is that a
mutator call throws.

## Entity: PageContext

A single nullable slot that carries the current page for the duration of one conversion.

**Namespace**: `CommonDoc\View`

| Member | Type | Notes |
|---|---|---|
| `set(Page $page)` | `void` | Writes the slot |
| `clear()` | `void` | Empties the slot; idempotent |
| `current()` | `Page|null` | The slot, or null when empty |

**State machine**:

| State | Event | Next state |
|---|---|---|
| empty | `set(page)` | holds page |
| holds page | `set(other)` | holds other page, replacing (a stale slot is a caller bug, not an error the class hides) |
| holds page | `clear()` | empty |
| empty | `clear()` | empty |

**Invariant**: at most one page is held. After every conversion, including a failed conversion, the
slot is empty, because the build loop clears it in a `finally` block.

## Transport: document data entry

| Key | Type | Written by | Read by |
|---|---|---|---|
| `commondoc.page` | `Page` | `PageContextListener` on `DocumentPreRenderEvent` | `DocumentViewRenderer` |

Absent or null means "no page". The listener writes only when `PageContext` holds a page, so a
document built outside the build loop keeps whatever it was given and a document that never saw a
page simply has no entry.

## Registry: template map entries

| Name | Template file | Variables supplied |
|---|---|---|
| `commondoc::document` | `templates/commondoc/document.phtml` | `documentBody` (`string`), `page` (`Page|null`) |
| `commondoc::layout` | `templates/commondoc/layout.phtml` | `content` (`string`), supplied by the view renderer |

`templates.layout` also names `commondoc::layout`, so the layout is not looked up per render.

## Cross-entity render sequence

1. The build loop calls `PageContext::set($page)`.
2. The build loop calls `ConverterInterface::convert($markdown)`.
3. CommonMark dispatches `DocumentPreRenderEvent`; `PageContextListener` writes
   `commondoc.page` into `$event->getDocument()->data`.
4. `DocumentViewRenderer` reads `commondoc.page`, renders the children through the child renderer,
   and renders `commondoc::document` with `documentBody` and `page`.
5. `LaminasViewRenderer` sets `content` on the layout model, renders `commondoc::layout`, and resets
   helper state.
6. The build loop calls `PageContext::clear()` in a `finally` block.

## Validation rules derived from the requirements

- The body template must never set `content`; `content` belongs to the layout alone (FR-005).
- The renderer must not fail when no page is present (FR-011).
- No component may mutate helper state or cache it between renders (FR-018).
- No new type may carry a `Holder`, `Manager`, `Helper`, `Util` or `Service` suffix (FR-019).
