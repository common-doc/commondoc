# Feature Specification: Document View Rendering

**Feature Branch**: `tyrsson-document-render-speckit`

**Feature Directory**: `specs/001-document-view-render`

**Created**: 2026-11-12

**Status**: Draft

**Input**: User description: "Replace the finished-HTML layout wrapping step with a rendering seam that renders one converted document through a real view render, so every view helper works for every Markdown node."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Override the document body by configuration alone (Priority: P1)

A site author wants the region that holds a rendered document body to match their own site. They name a replacement body template in site configuration, build the site, and get their template, with no code change, no subclass and no decorator.

**Why this priority**: A configuration-only override is the reason the seam exists. If a site has to write code to change the body, the seam has failed regardless of how well it renders.

**Independent Test**: Change only the configured body template name in a site fixture, build, and confirm the output comes from the replacement template. Delivers value on its own because it proves the body is a resolvable template rather than a hard-coded string.

**Acceptance Scenarios**:

1. **Given** a site whose configuration names its own document body template, **When** a document is rendered, **Then** the rendered output is produced by that template.
2. **Given** a site with no body template override, **When** a document is rendered, **Then** the body is produced by the package's own default body template.
3. **Given** a configured body template name that resolves to no file, **When** a document is rendered, **Then** rendering fails with an error that names the template it could not resolve.

---

### User Story 2 - Any view helper works inside rendered document output (Priority: P1)

An extension author adds a new kind of Markdown node. They want to render it with the same view helpers a layout uses, including helpers that only function inside a genuine view render, and they want existing extensions to keep producing output.

**Why this priority**: The current wrapping step operates on finished HTML, so no helper can run inside document output. Removing that limitation is the second half of the feature's purpose.

**Independent Test**: Render a document whose output calls a helper that fails outside a real view render, and render a document containing a custom node contributed by an installed extension. Both must succeed and both must appear in the output.

**Acceptance Scenarios**:

1. **Given** a document body whose template calls a view helper that only works inside a real view render, **When** the document is rendered, **Then** the helper produces output and rendering succeeds.
2. **Given** a document containing nodes contributed by an installed Markdown extension, **When** the document is rendered, **Then** every such node contributes output.
3. **Given** the document is rendered, **When** the body is produced, **Then** node output is obtained from the Markdown engine's own node handling rather than reimplemented by the seam.

---

### User Story 3 - Render a document with no page present (Priority: P2)

A consumer renders a document outside a site build, for example to preview a single file. No page is current.

**Why this priority**: A render that requires a page would make the seam unusable for previews and for any caller that only wants HTML. It is a smaller slice than the override or helper stories.

**Independent Test**: Render a document with no page supplied and confirm the body is produced and the render completes.

**Acceptance Scenarios**:

1. **Given** no current page, **When** a document is rendered, **Then** rendering completes and the body is produced.
2. **Given** no current page, **When** the body template refers to the page, **Then** the template can detect the absence without the render failing.

---

### User Story 4 - Render many documents in one process (Priority: P3)

A build renders hundreds of documents through one view renderer. Helper state set while rendering one document must not leak into the next.

**Why this priority**: A per-document renderer would satisfy the first three stories. This story exists so the seam is safe to share, which is the normal build shape.

**Independent Test**: Render ten documents in sequence through one renderer and compare each document's output to rendering it alone.

**Acceptance Scenarios**:

1. **Given** one shared renderer, **When** ten documents are rendered in sequence, **Then** each document's output equals the output that document produces when rendered first.
2. **Given** a document whose output populates a stateful helper, **When** the next document is rendered, **Then** the earlier content is absent from the later output.

---

### Edge Cases

- A document is rendered while a page is current, then a second document is rendered with no page: the second render must not see the first page.
- A conversion fails partway through: the page current at the start must not remain current for the next conversion.
- A document is built outside the build loop and rendered later: it must still carry the page it was built with, if any.
- A page has no children: rendering must complete and the page must report that it has none.
- A page has children several levels deep: every level must remain readable through the page's own read interface.
- A caller attempts to modify a page: the attempt must fail with an error that names immutability rather than silently doing nothing.
- A site's replacement body template sets the variable the layout reserves: that is a defect in the replacement, and the package's own body template must never do it.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Documents MUST be rendered inside the same view render that produces the layout, so every view helper available to a layout is available to document output.
- **FR-002**: The seam MUST obtain node output from the Markdown engine by delegating to that engine's own node rendering for the document's children, so every installed extension keeps contributing output.
- **FR-003**: The seam MUST NOT subclass, replace or reimplement the Markdown engine's HTML renderer, its converter or its converter contract.
- **FR-004**: The document body and the layout shell MUST be separate templates, named `commondoc::document` for the body and `commondoc::layout` for the shell.
- **FR-005**: The body template MUST receive the rendered body under the reserved variable `documentBody` and MUST NOT set the layout-reserved variable `content`.
- **FR-006**: Package configuration MUST register `commondoc::document` and `commondoc::layout` in the template map and MUST designate `commondoc::layout` as the layout.
- **FR-007**: Package configuration MUST NOT declare the retired template path stack key.
- **FR-008**: Template resolution MUST consult the active theme first, the template map second and a template path stack last, so a theme always wins and a configured map entry beats a filesystem path.
- **FR-009**: A site MUST be able to override `commondoc::document` through configuration alone, with zero code change.
- **FR-010**: The finished-HTML layout wrapping step MUST be removed from the build pipeline, and its class, its factory and its registrations MUST be deleted.
- **FR-011**: Rendering MUST tolerate the absence of a current page and MUST NOT fail when none is supplied.
- **FR-012**: The current page MUST be carried for the duration of exactly one conversion, written before the conversion starts and cleared after it finishes, including when the conversion fails.
- **FR-013**: The current page MUST reach the render as document data, so a document built outside the build loop and rendered later still sees the page it was built with.
- **FR-014**: A document MUST carry a page object that exposes its label, url, project slug, project name, version label, source path, template name, capture target, append flag, terminal flag and children.
- **FR-015**: The page object MUST be immutable, and every mutating operation MUST fail with an error that names immutability.
- **FR-016**: The page object MUST satisfy the framework's view model read contract: template access, variables access, single variable access, children access, child presence, capture target, append flag, terminal flag, count and iteration.
- **FR-017**: The page object MUST NOT be used as the render root, because the view layer writes template and variables onto the root and an immutable object cannot honour that.
- **FR-018**: Consumers MUST NOT have to reset view helper state themselves, and the seam MUST NOT cache helper state; the view layer's per-render helper reset is the only mechanism.
- **FR-019**: No type introduced by this feature may carry a `Holder`, `Manager`, `Helper`, `Util` or `Service` suffix.
- **FR-020**: Every type introduced by this feature MUST be typed, MUST use readonly state for value objects and MUST NOT expose magic accessors.
- **FR-021**: All new code MUST pass the repository's four static quality gates with zero findings and MUST add no baseline entry.
- **FR-022**: Every class and every exercised method introduced by this feature MUST carry test coverage metadata, and the mutation score gate MUST remain at 95.

### Key Entities

- **Document render**: One converted document plus zero or one current page. It is the unit the seam renders.
- **Page**: The identity and navigational placement of one document: label, url, project slug, project name, version label, source path, template name, capture target, append flag, terminal flag and children.
- **Template registry**: The configured mapping from template names to template files, plus the active theme and the fallback path stack, consulted in that precedence order.
- **Page context**: A single-slot carrier that holds the current page for the duration of one conversion only.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A site changes its document body by editing configuration only, and the built output reflects the change, with zero source files changed.
- **SC-002**: 100 percent of view helpers available to a layout are usable from document output, demonstrated by rendering at least one helper that fails outside a real view render.
- **SC-003**: Rendering a document with no current page completes successfully, demonstrated by a run that does not go through the build command.
- **SC-004**: Ten documents rendered in sequence through one renderer produce per-document output identical to rendering each document first.
- **SC-005**: Every custom node type contributed by an installed extension appears in the rendered output, with no extension-specific handling in the seam.
- **SC-006**: Zero occurrences of the removed wrapping step remain in the pipeline, its registrations or its documentation.
- **SC-007**: The full quality gate suite exits zero, and the mutation score stays at or above 95.

## Assumptions

- Site authors own site configuration and can add template files to their site.
- Only one document is converted at a time within a process, so a single-slot page carrier is sufficient and no concurrency control is required.
- The build loop is the only writer of the current page.
- The framework's view model contract remains available to consumers, and the page object is never the render root.
- Rendered output is HTML, the only output format the package produces today.
- The theme, template map and path stack resolvers already exist in the view rendering stack, and their precedence is as recorded.
- Removing the finished-HTML wrapping step, with its class, factory and registrations, is approved by the repository owner.
- An absent page is a supported state rather than an error, because rendering outside a build is a supported caller.
