# Tasks: Document View Rendering

**Input**: Design documents from `specs/001-document-view-render/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/](./contracts)

**Tests**: Required. The repository constitution makes tests mandatory, so every production task
below has a paired test task. Write the test first, confirm it fails, then implement.

**Organization**: Tasks are grouped by user story. The shared seam lives in the foundational phase,
because all four stories observe the same render.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: `US1` to `US4`, mapped to the user stories in [spec.md](./spec.md)
- Every task names the exact file it touches

## Path Conventions

Single package: `src/` for production code, `test/unit/` and `test/integration/` for tests,
`templates/commondoc/` for templates. PSR-4 `CommonDoc\` maps to `src/`, `CommonDocTest\` to
`test/unit/` and `CommonDocTestIntegration\` to `test/integration/`. Unit test files are flat in
`test/unit/`, matching every existing unit test.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Declare the dependency this feature depends on, and record a clean gate baseline so any
later failure is attributable to this feature.

- [ ] T001 Modify `composer.json`: add `"mezzio/mezzio-laminasviewrenderer": "^3.0"` to `require`,
  change the `php` constraint to `~8.4.1 || ~8.5.0 || ~8.6.0`, and leave `config.platform.php` at
  `8.4.99`.
- [ ] T002 Run `composer update mezzio/mezzio-laminasviewrenderer --with-all-dependencies` to
  refresh `composer.lock`, then confirm `vendor/bin/mago`, `vendor/bin/phpunit` and
  `vendor/bin/infection` exist.
- [ ] T003 [P] Record the baseline: run `vendor/bin/mago format`, `vendor/bin/mago lint`,
  `vendor/bin/mago analyze`, `vendor/bin/mago guard`, `composer test` and `composer test-integration`
  before any code change, and note that all exit zero.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The page model and the page transport. Every story observes a render that reads a page
from document data, so these types must exist first.

**Critical**: No user story work can begin until this phase is complete.

- [ ] T004 [P] Create `src/View/Page.php`: `final readonly class Page implements
  Laminas\View\Model\ModelInterface` in namespace `CommonDoc\View`, with promoted properties
  `string $label`, `string $url`, `string $projectSlug`, `string $projectName`,
  `string $versionLabel`, `string $sourcePath`, `string $template = 'commondoc::page'`,
  `string $captureTo = 'page'`, `bool $append = false`, `bool $terminal = false`, and
  `array $children = []` typed `list<Page>`. Named constructor arguments only. Implement the read
  side (`getTemplate`, `getVariables` returning the typed projection, `getVariable`, `getChildren`,
  `hasChildren`, `captureTo`, `isAppend`, `terminate`, `count`, `getIterator`). Every mutating
  `ModelInterface` method declares return type `static` and throws `LogicException` naming
  immutability and the page label. No `__get`, `__set` or `__call`.
- [ ] T005 [P] Create `test/unit/PageTest.php` in namespace `CommonDocTest`, with
  `#[CoversClass(Page::class)]` and one `#[CoversMethod]` per exercised method. Assert: the class is
  an instance of `ModelInterface`; each read method returns its documented value; `getVariables()`
  contains no `content` key; `count()` equals `iterator_count(getIterator())`; each of the seven
  mutators throws `LogicException` whose message names immutability; every `#[Test]` method uses
  `static::assert*`.
- [ ] T006 [P] Create `src/View/PageContext.php`: final class in namespace `CommonDoc\View` with
  `set(Page $page): void`, `clear(): void` and `current(): Page|null`. One slot, no static state, no
  stack, no service locator.
- [ ] T007 [P] Create `test/unit/PageContextTest.php` with `#[CoversClass(PageContext::class)]` and
  `#[CoversMethod]` for `set`, `clear` and `current`. Assert the empty-to-set-to-cleared transitions,
  that `clear()` on an empty context is safe, that `current()` is null when nothing is set, and that
  `set` replaces rather than accumulates.
- [ ] T008 Create `src/Container/PageContextFactory.php`: `final readonly class
  PageContextFactory implements FactoryInterface` in namespace `CommonDoc\Container`, returning a
  `PageContext`. The service is shared by default, so one slot exists per process.
- [ ] T009 Create `test/unit/PageContextFactoryTest.php` with
  `#[CoversClass(PageContextFactory::class)]` and `#[CoversMethod]` for `__invoke`. Assert it returns
  a `PageContext` and that two resolutions from the same container return the same instance.
- [ ] T010 Create `src/View/PageContextListener.php`: final class in namespace `CommonDoc\View`
  taking `PageContext` by constructor injection, exposing
  `__invoke(DocumentPreRenderEvent $event): void`. When `PageContext::current()` is not null, write
  it to `$event->getDocument()->data` under the key `commondoc.page`. When it is null, write nothing.
- [ ] T011 Create `test/unit/PageContextListenerTest.php` with
  `#[CoversClass(PageContextListener::class)]` and `#[CoversMethod]` for `__invoke`. Use
  `createStub()` for the event and page doubles. Assert the data key is written when a page is
  current, that a pre-existing page in document data survives when no page is current, and that no
  exception is raised when the context is empty.
- [ ] T012 Create `src/Container/PageContextListenerFactory.php`: `final readonly class
  PageContextListenerFactory implements FactoryInterface` in namespace `CommonDoc\Container`,
  resolving `PageContext` from the container and returning the listener.
- [ ] T013 Create `test/unit/PageContextListenerFactoryTest.php` with
  `#[CoversClass(PageContextListenerFactory::class)]` and `#[CoversMethod]` for `__invoke`, using a
  `createStub()` container that returns a `PageContext`.
- [ ] T014 Modify `src/ConfigProvider.php`: add `PageContext::class => PageContextFactory::class` and
  `PageContextListener::class => PageContextListenerFactory::class` to the `factories` array of
  `getDependencies()`, with the docblock types corrected to match the concrete shape.

**Checkpoint**: The page model and the page transport exist, are tested and are wired. Stories can
now proceed.

---

## Phase 3: User Story 1 - Override the document body by configuration alone (Priority: P1) MVP

**Goal**: A converted document renders through a real view, with the body and the layout as separate
resolvable templates, so a site replaces the body through `templates.map` configuration alone.

**Independent Test**: Point a fixture `templates.map` entry for `commondoc::document` at a
replacement template, render a document, and confirm the output comes from the replacement. Then
remove the entry and confirm the packaged body renders again.

### Tests for User Story 1

- [ ] T015 [P] [US1] Create `test/unit/DocumentViewRendererTest.php` with
  `#[CoversClass(DocumentViewRenderer::class)]` and `#[CoversMethod]` for `render`. Use
  `createStub()` for `TemplateRendererInterface`, `ChildNodeRendererInterface` and the document
  double. Assert: the template render result is returned unchanged; `renderNodes` is called with the
  document's children; a non-`Document` node raises `InvalidArgumentException`.
- [ ] T016 [P] [US1] Create `test/unit/DocumentViewRendererFactoryTest.php` with
  `#[CoversClass(DocumentViewRendererFactory::class)]` and `#[CoversMethod]` for `__invoke`. Use a
  `createStub()` container returning a `TemplateRendererInterface` stub.
- [ ] T017 [P] [US1] Create `templates/commondoc/layout.phtml`: the layout shell that echoes
  `$this->content`, with a document title region driven by `$this->headTitle()`. It must not assume a
  page, because the no-page render also wraps in it.

### Implementation for User Story 1

- [ ] T018 [US1] Create `src/View/DocumentViewRenderer.php`: `final readonly class
  DocumentViewRenderer implements League\CommonMark\Renderer\NodeRendererInterface` in namespace
  `CommonDoc\View`, constructed with `TemplateRendererInterface`. `render()` rejects a node that is
  not a `League\CommonMark\Node\Block\Document` with `InvalidArgumentException`, obtains the body
  from `$childRenderer->renderNodes($node->children())`, reads the page from
  `$node->data->get('commondoc.page')` with a null default, and returns
  `$this->renderer->render('commondoc::document', ['documentBody' => $body, 'page' => $page])`. No
  layout handling, no helper-state handling, no caching, no `content` key.
- [ ] T019 [US1] Create `src/Container/DocumentViewRendererFactory.php`: `final readonly class
  DocumentViewRendererFactory implements FactoryInterface` in namespace `CommonDoc\Container`,
  resolving `TemplateRendererInterface` from the container.
- [ ] T020 [US1] Modify `src/ConfigProvider.php`: replace the `view_manager.template_path_stack`
  section of `getViewConfig()` with a `templates` section holding `map` (`commondoc::document` and
  `commondoc::layout`, mapped to `templates/commondoc/document.phtml` and
  `templates/commondoc/layout.phtml`) and `layout` (`commondoc::layout`); add
  `DocumentViewRenderer::class => DocumentViewRendererFactory::class` to the factories array; remove
  the middleware from `getBusConfig()` pipeline middleware; remove its factory entry; rewrite the
  class docblock so the documented pipeline priorities match the pipeline that remains; update the
  docblocked config shape types to the new view configuration.
- [ ] T021 [US1] Modify `src/Container/EnvironmentFactory.php`: resolve `DocumentViewRenderer` and
  `PageContextListener` from the container, then call
  `$environment->addRenderer(Document::class, $viewRenderer, 10)` and
  `$environment->addEventListener(DocumentPreRenderEvent::class, $pageContextListener)` before
  returning the environment. Inject nothing else and construct no collaborator inline.
- [ ] T022 [US1] Rewrite `templates/commondoc/document.phtml` as the body template: echo
  `$documentBody` inside the body region, read the optional page through the `page` variable with a
  null check, and never assign or echo `content`.
- [ ] T023 [US1] Delete `src/Middleware/LayoutWrappingMiddleware.php`,
  `src/Container/LayoutWrappingMiddlewareFactory.php`,
  `test/unit/LayoutWrappingMiddlewareTest.php` and
  `test/unit/LayoutWrappingMiddlewareFactoryTest.php`. The removal is owner approved.
- [ ] T024 [US1] Modify `test/unit/ConfigProviderTest.php`: assert the two `templates.map` entries,
  the `templates.layout` value, the absence of the retired template path stack key, the presence of
  the three new factory entries and the absence of the deleted middleware in the pipeline and the
  factories array.
- [ ] T025 [US1] Modify `test/integration/ConfigProviderWiringTest.php` to reflect the removed
  middleware registration and the new view configuration, resolving the new services from a real
  container.
- [ ] T026 [US1] Modify `test/unit/EnvironmentFactoryTest.php` to assert the returned environment
  renders `Document` through `DocumentViewRenderer` and that the page context listener is registered
  for `DocumentPreRenderEvent`.
- [ ] T027 [US1] Create `test/integration/DocumentViewRenderingTest.php` in namespace
  `CommonDocTestIntegration`: build a real container, render a document through the real
  `LaminasViewRenderer`, and assert three cases. First, a site `templates.map` entry for
  `commondoc::document` pointing at a temporary body template produces that template's marker with no
  code change. Second, with no override, the packaged body renders and the output is wrapped by the
  layout. Third, a configured body template name that resolves to nothing raises the renderer's
  template-not-found exception, and the exception message names the template.

**Checkpoint**: User Story 1 works and is independently testable. The seam renders a real view, and
the body is overridable by configuration.

---

## Phase 4: User Story 2 - Any view helper works inside rendered document output (Priority: P1)

**Goal**: Helper output and extension-contributed node output both reach the rendered document,
because node output comes from the Markdown engine and the body runs inside a genuine view render.

**Independent Test**: Render a body template that calls a helper which only works inside a real view
render, and render a document containing a node contributed by an installed extension. Both must
appear in the output.

The implementation of this story is the single delegation call in T018 and the view render in T018.
Its tasks pin the guarantee, so a future refactor that reintroduces HTML post-processing fails.

### Tests for User Story 2

- [ ] T028 [US2] Add delegation cases to `test/unit/DocumentViewRendererTest.php`: assert
  `renderNodes` receives exactly `$node->children()` and nothing else; assert the body string handed
  to the template is whatever the child renderer returned, byte for byte; assert a child renderer
  returning an empty string still produces a rendered document.
- [ ] T029 [US2] Add a helper case to `test/integration/DocumentViewRenderingTest.php`: render through
  the real container with a body template that invokes a stateful Laminas view helper, and assert the
  helper's rendered output appears inside the body.
- [ ] T030 [US2] Add an extension case to `test/integration/DocumentViewRenderingTest.php`: convert a
  document containing a node contributed by an installed Markdown extension and assert that node's
  output is present; also assert the container still returns the library's own `HtmlRenderer` and
  `ConverterInterface` implementations, proving nothing was subclassed or replaced.

**Checkpoint**: Helper output and extension output are proven, not merely assumed.

---

## Phase 5: User Story 3 - Render a document with no page present (Priority: P2)

**Goal**: A render performed outside a build completes. No page is required anywhere.

**Independent Test**: Convert a document without ever writing `PageContext`, and confirm the body is
produced and the render completes.

- [ ] T031 [US3] Tighten `src/View/DocumentViewRenderer.php` so the page read is explicitly nullable:
  `commondoc.page` is read with a null default, the `page` variable is always supplied to the body
  template, and no branch throws or short-circuits when it is null. Declare the accepted type as
  `Page|null` so static analysis proves the tolerance rather than assuming it.
- [ ] T032 [US3] Add no-page cases to `test/unit/DocumentViewRendererTest.php`: a document with no
  `commondoc.page` entry renders and receives `page` as null; a document whose entry is explicitly
  null renders identically.
- [ ] T033 [US3] Add a no-page case to `test/integration/DocumentViewRenderingTest.php`: render
  without touching `PageContext` and assert a full document, body plus layout, with no exception.

**Checkpoint**: Stories 1, 2 and 3 all hold. A preview render with no site model works.

---

## Phase 6: User Story 4 - Render many documents in one process (Priority: P3)

**Goal**: One shared renderer serves hundreds of documents with no state leaking between them.

**Independent Test**: Render ten documents in sequence through one renderer and compare each output
against rendering it first.

- [ ] T034 [US4] Add a sequence case to `test/integration/DocumentViewRenderingTest.php`: render ten
  documents with distinct headings and distinct pages through one container and one renderer, and
  assert each output contains only its own content, with no placeholder or title content from an
  earlier render.
- [ ] T035 [US4] Add a no-retained-state case to `test/unit/DocumentViewRendererTest.php`: two
  sequential renders of different documents produce their own bodies, and the renderer performs
  exactly one template render per document, proving no per-render state is held in the seam.

**Checkpoint**: All four user stories are independently functional.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T036 [P] Run `vendor/bin/mago format` and `vendor/bin/mago lint`, and confirm both exit zero
  with no new baseline entry for new code.
- [ ] T037 Run `vendor/bin/mago analyze` and `vendor/bin/mago guard`, and confirm both exit zero with
  no new baseline entry. Correct declarations that the analyzer rejects; never reshape the code to
  satisfy it.
- [ ] T038 Run `composer test`, `composer test-integration` and `composer test-coverage`, and confirm
  every new class is covered and every test class carries `#[CoversClass]` and `#[CoversMethod]`.
- [ ] T039 Run `vendor/bin/infection` and confirm `min_msi` and `min_covered_msi` are at least 95.
- [ ] T040 Run `vendor/bin/roave-backward-compatibility-check` and record the result. Deleting the
  `@api` middleware is a backward-compatibility break, approved by the owner; the package has not
  reached 1.0, so the break is accepted rather than worked around.
- [ ] T041 Update `README.md` with the configuration-only override recipe: the `commondoc::document`
  map entry, the precedence order theme 100, map 50, path stack 1, and the rule that the body
  template must not set `content`.
- [ ] T042 Walk scenarios 1 to 6 of `specs/001-document-view-render/quickstart.md` against the
  implemented branch and record the outcome of each.
- [ ] T043 Sweep every file this feature touches, plus `specs/001-document-view-render/`, for
  forbidden characters and naming: no em dash or en dash, LF line endings only, no new type carrying
  a `Holder`, `Manager`, `Helper`, `Util` or `Service` suffix, singular names, `Event` suffix on
  events and `Listener` on listeners.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies, start immediately.
- **Foundational (Phase 2)**: Depends on Setup. Blocks all four stories.
- **User Stories (Phases 3 to 6)**: All depend on Phase 2. US1 must land before US2, US3 and US4 can
  be validated, because all three observe the render US1 introduces.
- **Polish (Phase 7)**: Depends on every story that is in scope.

### User Story Dependencies

- **US1 (P1)**: Requires Phase 2 only. Delivers the seam.
- **US2 (P1)**: Requires US1. Its guarantee is a property of the US1 renderer, so its tasks are
  verification tasks.
- **US3 (P2)**: Requires US1. Adds an explicit nullable contract and pins it.
- **US4 (P3)**: Requires US1. Pins sharing safety.

### Within Each Story

- Test tasks first, and they must fail before the implementation task runs.
- The renderer lands before the factory, because the factory instantiates it.
- The factory lands before the container registration, because the registration names the factory.
- Templates land before the integration test that renders them.
- The deletion lands with the configuration change that removes the registrations, so no commit
  leaves a pipeline entry pointing at a missing class.

### Parallel Opportunities

- T004, T005, T006 and T007 are four independent new files.
- T008 and T010 are independent new files; T012 depends on T010.
- T015, T016 and T017 are three independent new files.
- T036 can run alongside T037 only after all source work is complete.

---

## Parallel Example: Phase 2

```text
Task: "Create src/View/Page.php"
Task: "Create test/unit/PageTest.php"
Task: "Create src/View/PageContext.php"
Task: "Create test/unit/PageContextTest.php"
```

## Parallel Example: Phase 3

```text
Task: "Create test/unit/DocumentViewRendererTest.php"
Task: "Create test/unit/DocumentViewRendererFactoryTest.php"
Task: "Create templates/commondoc/layout.phtml"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 and Phase 2.
2. Phase 3.
3. Stop and validate US1 independently: override by configuration alone, packaged default body,
   missing template error.
4. Ship as the first merge-commit pull request, without merging it.

### Incremental Delivery

1. Foundation, then US1, then validate.
2. US2, then US3, then US4. Each adds a guarantee and each is a separate commit, and each step is its
   own branch and merge-commit pull request per the constitution.
3. Polish last, as its own branch.

### Scope discipline

- No source, test, configuration or template file outside the paths named in this task list.
- No file is renamed or moved. The four recorded deletions are the only deletions, and they are
  owner approved.
- No pull request is merged without an explicit instruction.

---

## Notes

- [P] marks tasks that touch different files and depend on nothing incomplete.
- Every new test class needs `#[CoversClass]` and one `#[CoversMethod]` per exercised method, or the
  coverage-metadata requirement fails the suite.
- `createStub()` for value doubles, `createMock()` only where `expects()` is configured.
- Union types list `null` last.
- Commit messages use the conventional form, carry the signoff the constitution requires, and contain
  no em dash or en dash.
