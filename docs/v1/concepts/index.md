# Concepts

Two ideas carry the whole tool. Everything else is a consequence of them.

| Concept | Question it answers | Page |
| --- | --- | --- |
| Document | What is a document, and how is it found? | [Documents](document.md) |
| Path | Where does a document live, on disk and on the site? | [Paths and files](paths.md) |

## Why the split matters

league/commonmark converts one document at a time. It takes a string, produces a
tree, and renders that tree to HTML. It has no notion of a second document, of a
directory, or of a site.

That single fact decides the architecture. Anything that needs to see more than
one document at once, such as discovery or a link to a sibling page, has to
happen outside the conversion. Anything that concerns one document can happen
inside it, during the parse, where CommonMark offers an event to hook.

- [ ] Discovery and link resolution live outside the conversion
- [ ] Per-document work hooks the parse
- [x] The two are kept in separate passes

The result is a build made of passes around a one-document engine, rather than a
single converter that has been taught about sites.
