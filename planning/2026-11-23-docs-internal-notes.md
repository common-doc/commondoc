# Internal notes moved out of the documentation

Rationale and unfinished work that was sitting in `docs/v1`, gathered here on 2026-11-23. None
of it is behaviour a site author acts on.

## Why the concepts are split

league/commonmark converts one document at a time. It takes a string, produces a tree, and
renders that tree to HTML. It has no notion of a second document, of a directory, or of a site.

That single fact decides the architecture. Anything that needs to see more than one document at
once, such as discovery or a link to a sibling page, has to happen outside the conversion.
Anything that concerns one document can happen inside it, during the parse, where CommonMark
offers an event to hook.

- [ ] Discovery and link resolution live outside the conversion
- [ ] Per-document work hooks the parse
- [x] The two are kept in separate passes

The result is a build made of passes around a one-document engine, rather than a single
converter that has been taught about sites.

## Parsing

Moved from `concepts/document.md`. The tree is the converter's internal contract, not something
a site author touches.

Once the set is known, each document is read and converted. The conversion produces an abstract
syntax tree, and everything downstream reads only that tree: the source string is gone by then.
For CommonMark the tree is the document.

The tree is an object graph of typed nodes. Every node has a class a renderer can key on, a
position in a mutable structure, and a data bag for arbitrary values. That is what lets a
site-specific concern ride on the tree without CommonMark learning the vocabulary: a value can
be attached to a node, or a new node type can be declared along with its renderer.

## Known gaps

Moved from `theme.md`. The user-facing half of the first item is stated in the navigation
section; the rest is why it has not been closed.

- Components that are not released versions are not in the Components menu. A release gets its
  own route at the component's address, but an unversioned component's index page is already a
  document route, and a second route at that path would be refused as a duplicate. They do get
  a card on the home page. Listing them would mean tagging that existing route for both menus,
  which would show its label as `index` rather than the component's name.
- The home page is written only for a site with components. A site made of one root mounted at
  `/` has none, because its own index page is the home page.
- A per-page table of contents is not rendered. The headings are collected and available in
  process, but nothing passes them to a template.
- `PageContext::sections()` is not handed to the layout, because nothing renders a per-page
  table of contents.

## Extensions

Moved from `extensions.md`. Nothing is planned that is not built; extensions that are wanted but
undecided are tracked in the repository's issues rather than in the documentation.
