# Phase 0 Research: Document View Rendering

**Feature**: [spec.md](./spec.md)

**Date**: 2026-11-12

**Status**: Complete. Every entry below is a settled decision or a verified library behaviour. Nothing here is inferred from behaviour that was not read.

## D1. The seam is a node renderer for the Document node

**Decision**: Implement `League\CommonMark\Renderer\NodeRendererInterface` in
`CommonDoc\View\DocumentViewRenderer` and register it in `EnvironmentFactory` for
`League\CommonMark\Node\Block\Document` at priority 10.

**Rationale**: The Document node is the one point that sees the entire converted tree. CommonMark's
default `DocumentRenderer` is registered at priority 0, so priority 10 wins without touching the
converter. `HtmlRenderer`, `MarkdownConverter` and `MarkdownConverterInterface` remain exactly as
the library ships them.

**Alternatives considered**:

- Subclass `HtmlRenderer`: it is `final`, and replacing it discards every extension renderer.
- Replace or reimplement `MarkdownConverterInterface`: forbidden by the feature requirements.
- Post-process a finished HTML string: the current approach, and the reason no view helper can run
  inside document output.

## D2. Body output comes from CommonMark itself

**Decision**: Call `$childRenderer->renderNodes($node->children())` using the
`ChildNodeRendererInterface` that CommonMark passes to `NodeRendererInterface::render()`, and pass
the result into the body template as `documentBody`.

**Rationale**: Every installed extension keeps contributing output because dispatch stays inside
CommonMark. The seam never holds a list of extensions or node types.

**Alternatives considered**:

- Render each child by inspected class: duplicates CommonMark's renderer dispatch and drifts.
- Ask the environment to render the children: recurses into the Document renderer and loops.
- Concatenate `(string) $child` manually: skips renderer priority and extension renderers.

## D3. The view renderer is injected as its interface

**Decision**: `DocumentViewRenderer` receives `Mezzio\Template\TemplateRendererInterface`. The
container supplies the `Mezzio\LaminasView\LaminasViewRenderer` instance.

**Rationale**: `LaminasViewRenderer` is declared `final` in 3.0.0, the version `composer.lock` pins,
so it can neither be subclassed nor doubled. The interface is the only injection contract that
keeps the seam unit testable, and dependency injection still supplies the concrete class that
performs layout wrapping and helper reset.

**Alternatives considered**:

- Type the constructor against the final class: forces every test of the seam to boot a real
  environment, which moves a unit contract into `test/integration/`.
- Construct the renderer inside the seam: violates the inject-a-factory rule and hides the
  dependency.

## D4. Layout wrapping and helper reset are inherited, not implemented

**Decision**: The package implements no layout step and no helper-state reset.

**Rationale**: `LaminasViewRenderer::render()` renders the body, resolves the layout, sets `content`
on the layout model, renders the layout, and then calls `$this->helpers->resetState()`.
`HelperPluginManager` resets every `StatefulHelperInterface`, which includes the whole `Placeholder`
family. A renderer shared across many documents is therefore already safe.

**Alternatives considered**:

- A hand-rolled layout step: the finished-HTML wrapping that this feature removes.
- Building a renderer per document: defeats sharing and adds no safety the library does not already
  provide.
- Caching or resetting helper state in this package: duplicates library behaviour and can drift.

## D5. Page is a readonly implementation of the view model contract

**Decision**: `final readonly class CommonDoc\View\Page implements
Laminas\View\Model\ModelInterface`.

**Rationale**: `Laminas\View\Model\ViewModel` is `final` and stores its variables in a `mixed` bag
behind `__get` and `__set`, neither of which static analysis can check. `ModelInterface` is the real
contract and is not sealed, and it is what templates and helpers consume.

**Alternatives considered**:

- Extend `ViewModel`: it is final.
- Ship a plain DTO: satisfies no consumer that expects a view model, and every template would need
  a different shape.
- Add magic accessors: excluded by the typed-code rule.

## D6. Page never enters a rendered model tree

**Decision**: Every render has a disposable `ViewModel` as its root. The page reaches templates only
as the `page` variable. Page children stay inside the page tree and are read through the page's own
read interface.

**Rationale**: `LaminasViewRenderer::render()` calls `mergeViewModel()` on the root, which calls
`setVariables()` and `setTemplate()`; a readonly object cannot honour either. Reading 3.0.0 also
shows `renderRecursively()` calling `beforeRender()` on every child, and `beforeRender()` performs
the same two writes. A page attached as a view model child would therefore throw. Keeping the page
entirely out of a rendered model tree satisfies the intent, which is that a page is never the render
root and is never mutated by the view layer.

**Alternatives considered**:

- Make Page mutable: loses the readonly guarantee that the feature exists to establish.
- Add a mutable adapter that wraps Page as a child: a second type with no behaviour this feature
  needs.

**Note for review**: this is the one place where the settled wording ("as a child node") is
narrower in practice than written. The verified library behaviour is documented above so the
implementer does not attach a readonly Page to a rendered model.

## D7. The page travels through PageContext and document data

**Decision**: `CommonDoc\View\PageContext` holds a nullable page. The build loop writes it before
each conversion and clears it after. `CommonDoc\View\PageContextListener` handles
`League\CommonMark\Event\DocumentPreRenderEvent` and writes the current page into
`$event->getDocument()->data` under the key `commondoc.page`. `DocumentViewRenderer` reads that key
and passes the page to the body template.

**Rationale**: `ConverterInterface::convert(string)` carries no context, so a page cannot be passed
by argument. Document data is the transport because it survives a document that was built outside
the build loop and rendered later.

**Alternatives considered**:

- A static or globally reachable holder: untestable and invisible in the type system.
- Widening `ConverterInterface`: forbidden, and it is not this package's interface.
- Wrapping the converter: adds a type whose only job is to smuggle an argument, and the event
  listener already has a supported hook.

**Naming**: the class is `PageContext`. `PageContextHolder` was drafted earlier and rejected by the
owner. No `Holder`, `Manager`, `Helper`, `Util` or `Service` suffix appears anywhere in this feature.

## D8. Template resolution uses the map, with the theme first

**Decision**: `ConfigProvider::getViewConfig()` contributes `templates.map` with
`commondoc::document` and `commondoc::layout`, and `templates.layout` with `commondoc::layout`. The
dead `view_manager.template_path_stack` key is removed.

**Rationale**: `webware-theme` orders its resolver chain theme 100, template map 50, template path
stack 1. The map is more performant than a stack, and it is the mechanism by which a site overrides
`commondoc::document` through configuration alone with zero code change. That override capability is
a hard requirement.

**Alternatives considered**:

- Path stack only: slower, and an override becomes a file-precedence game rather than a config
  entry.
- A delegator or listener that rewrites template names: a code change, which is exactly what must
  not be required.

## D9. The finished-HTML wrapping step is deleted

**Decision**: Delete `CommonDoc\Middleware\LayoutWrappingMiddleware`, its factory, its
`ConfigProvider` entries in both the pipeline and the factories map, and its tests.

**Rationale**: The middleware operates on a finished string, so it can never produce helper output.
It also occupies a pipeline priority that no longer means anything. The repository owner approved
this removal as part of the v1 decisions.

**Alternatives considered**:

- Leave it as a pass-through: dead code, and a misleading pipeline position for the next reader.

## D10. Dependency and platform declarations

**Decision**: Keep `config.platform.php` at `8.4.99`, declare support for
`~8.4.1 || ~8.5.0 || ~8.6.0`, and add `mezzio/mezzio-laminasviewrenderer: ^3.0` as a direct
requirement.

**Rationale**: The package now imports `TemplateRendererInterface` and `LaminasViewRenderer`
directly, so the package that provides them must be a direct requirement instead of an accident of
`webware-theme`'s tree. The engine major is constrained because this design depends on the finality
and the per-render reset verified in 3.0.0.

**Note**: `webware-ci.json` lists PHP 8.4 and 8.5 only. Adding 8.6 to the CI matrix is a CI policy
change and is outside this feature.

## D11. Naming and structure of the new namespace

**Decision**: The new types live in `CommonDoc\View`: `DocumentViewRenderer`, `Page`,
`PageContext`, `PageContextListener`. Their factories live in `CommonDoc\Container`, matching every
other factory in the package.

**Rationale**: The namespace segment does not repeat its parent, the names are singular, and the
event listener carries the `Listener` suffix because it listens. Factories stay in `Container`,
which is the existing convention and where `EnvironmentFactory` already resolves collaborators.

**Alternatives considered**:

- `CommonDoc\Renderer`: reads as the renderer namespace, but also holds the page model and the
  context, neither of which renders.
- Per-type subnamespaces (`CommonDoc\View\Page\Page`): repeats the parent segment.

## Open points

- `commondoc::page`, the default template name on `Page`, is not registered in the map and no page
  template ships in v1. Nothing renders `Page` directly, so the default is a placeholder until a
  page template exists.
