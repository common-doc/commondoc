# Site composition: the second producer

**Date**: 2026-11-19
**Component**: `common-doc/commondoc`
**Target site**: `webinertia/docs.web-ware.net`, which publishes the Webware
component documentation at a domain of its own, separate from the company site.

A proposal, not a decision. It records what a real published site needs, what
was measured against the live reference site, and the two forks that need the
owner's ruling before any code. Nothing here authorises an implementation.

---

## What the site has to be

The owner's requirement, restated:

- `/` is a landing page the owner designs freely. For the Webware site that is
  Bootstrap 5.3 cards, one per repository, because each repository is a
  component.
- A card links to that component's default-branch entry.
- A component entry carries a version picker when the component has previous
  releases.
- The table of contents belongs on the component page, not on the landing page.

That shape is confirmed by the site it replaces. Fetched from
`https://docs.webinertia.net/`:

- `/` lists one entry per component, each with a description and
  "Latest version: v2".
- `/message-bus/` is not a page; it redirects to `/message-bus/v2/`.
- `/message-bus/v2/` is the component index, and its "Contents" list is the
  table of contents.
- Detail pages are suffix bearing (`/message-bus/v2/flow-charts.html`), so that
  site renders with `PathType::File`, not this package's `Directory` default.

## What the reference site's `<head>` revealed

The landing page is not only HTML. Fetched raw, its head carries:

```
<link rel="icon"      href="/assets/logo.<hash>.svg">
<link rel="stylesheet" href="/assets/asciidoctor.<hash>.css">
<link rel="stylesheet" href="/assets/site.<hash>.css">
<link rel="stylesheet" href="/assets/accent.<hash>.css">
<script src="/assets/theme.<hash>.js" defer></script>
```

So a published site also needs **site level assets at addresses the builder
owns**, with content hashes in their names. That is the part this proposal turns
on, and it is larger than the landing page itself.

## The diagnosis

`commondoc` has exactly one producer: walked roots, which yield documents and
assets. `CommonDoc\Input\Root` binds two separate ideas together, a directory to
walk and a prefix in the address space, so every existing output has both.

The site's own chrome has an address and no directory. It therefore does not fit
`Root`, and the mismatch surfaces as an overlap refusal only because the first
attempt expressed it as a root.

Measured (`SiteConfig::assertExclusive` via `Root::overlaps()`, which compares
mount prefixes):

| Shape | Refused |
| --- | --- |
| a root at `/` plus a component at `/webware-console/` | yes |
| a component root plus its version root | yes |
| two versions of one component | no |
| two components | no |

Relaxing that rule to admit the first row would be the wrong fix, and this was
checked rather than assumed:

- **Documents have a precise guard.** `BuildSiteHandler::seed()` registers one
  route per document and the collector refuses a duplicate published path
  (`DuplicateRouteException`), so two documents at one address are caught
  exactly.
- **Assets have no guard at all.** `AssetCopyMiddleware` writes each asset at its
  own published path through `FileWriter::writeTree` with no existence check, so
  two roots providing one asset path overwrite each other, ordered by the scan.

`assertExclusive` is therefore the only thing protecting asset address ownership,
and it stays as it is. The chrome is simply not a root.

## Why the view integration is the seam

`webware-theme` already defines the asset contract for a served application:

- `$this->asset('theme.css')` resolves a name to a URL.
- Anything not absolute becomes `/theme/<theme>/<value>`, where `<theme>` is the
  theme that *defined* the name, so fallback is per name.
- Files live under `public/theme/<theme>/{css,js,img,fonts}`.
- Publishing into `public/` is delegated to the Composer installer plugin
  (`webinertia/project-tracking#6`), which does not exist yet.

For a static site, `public/` is the build output directory. The theme layer
therefore already owns the asset half of the chrome, and has no counterpart for
being *written into a build* rather than served by a web server. The landing page
is the HTML half of the same concern. Both are renders through the view layer,
which is why laminas view had to be the integration point rather than something
added later.

## Proposal

Add a second producer, referred to here as the **site chrome**: a phase that
publishes addressed output the build generates rather than walks.

- The landing page: a view template plus the component list.
- The theme's static files: named by the theme, not by a root.
- Both are addressed through the existing `DocumentRoot` translation, so base
  path and path type apply without a second set of rules. `file('index.md')`
  already answers `index.html` under both path types, so the landing page needs
  no new translation.
- Both claim their addresses in the same route collector the documents use, so
  an authored `index.md` at `/`, or a component that ships a `theme/` directory,
  is refused with a duplicate address error rather than silently replacing one
  of them.
- `Root`, `Root::overlaps()` and `SiteConfig::assertExclusive()` are untouched.
  The eight staged component trees build exactly as they do today, with the
  chrome layered on top.

## Forks awaiting the owner's ruling

These are dependency direction and template ownership, not implementation
detail, which is why no code was written.

1. **Who owns the theme's static files in a build?** Either `webware-theme`
   exposes its file list and `commondoc` publishes it (dependency points from
   `commondoc` to the theme, and a theme swap changes the site's chrome), or
   `commondoc` takes an `assets` configuration naming directories (the theme
   stays out of the build and the workflow supplies the files), or the theme
   supplies a default that configuration may extend.

2. **Where does the landing page template live?** Either a theme template, so a
   theme swap redesigns the landing page with the rest of the chrome, or a
   `commondoc` template a site overrides through the existing `templates.map`,
   independent of the theme, or a theme template with a site level override.

A third question follows from the first two but is smaller: when the chrome
claims `/`, should an authored `index.md` at a root mounted at `/` be refused as
a duplicate, or should authored content win? Refusing is the consistent answer,
because it is the same rule documents already get, and it was the assumption the
proposal was written against.

## Handed to the version model, not resolved here

Card links go to a component's default-branch entry, and the reference site
redirects `/message-bus/` to `/message-bus/v2/`. Something has to own the bare
component address. If version roots mount at `/component/<version>/`, then
`/component/` is content free and needs either a redirect or a generated index.
That is the version model (item 3), not the address space, and it is recorded
here only so the seam is not lost.

## Evidence gathered while staging

Eight component trees were staged and built to prove the composition works
before any of this is designed:

`webware-configmanager`, `webware-filewriter`, `webware-console`,
`webware-theme`, `webware-message`, `webware-mailer`, `webware-migration`,
`webware-phpdb`.

All of them keep Markdown under `docs/v1`, and all of them are plain `.md` with
no assets of their own. A local build of the staged tree wrote all 24 documents
plus the copied assets and exited `0`. Components with no `docs` directory at all
(`webware-navigation`, `message-bus`, `webware-core`, `webware-log`,
`query-param-router`, `webware-htmx`, `webware-acl`, `webware-validator`,
`webware-tools`, and others) are the reason the site's component list cannot be
derived from local paths alone.

## Related

- `2026-11-18-layer-walkthrough.md` holds the decisions, including D37 (the
  target is configuration) and D38 (the two root entry shapes).
- `2026-11-19-layer-findings.md` holds D39 (a heading carries its own slug) and
  D40 (a document's headings are collected as link targets).
