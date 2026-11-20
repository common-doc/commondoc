# Site configuration

A build is described by one section, keyed by `CommonDoc\SiteConfig::class`, so a
deployment states its own values in its own autoload file and this package never
names one.

| Key | Meaning |
| --- | --- |
| `basePath` | The path the site is served at, such as `/` or `/commondoc/` |
| `pathType` | `Directory` publishes `guide/setup.md` at `/guide/setup/`, served by an `index.html` inside it. `File` publishes it at `/guide/setup.html` |
| `output` | The directory a build writes to |
| `target` | Where a repository keeps its documentation, relative to that repository's root |
| `roots` | The source roots the site is composed from |
| `projectName` | The display name every page carries, or empty for none |
| `projectSlug` | The identifier every page carries, or empty for none |
| `versionLabel` | The version this build documents, or empty for none |

## Composition

A site is one published site, and it may be composed from several repositories.
Rather than a second kind of build, each repository is a root, and two values say
everything about it:

| Value | Question it answers | Example |
| --- | --- | --- |
| `target` | Where inside the repository are its sources? | `docs/v1` |
| `mount` | Where does it appear in the published site? | `/email/` |

They are orthogonal. A repository can be mounted at any prefix regardless of its
target, and several repositories can share a target.

### Mount

`mount` is the prefix a root publishes at. A file is published at the mount
followed by its path inside that root, so once the mount is applied everything
downstream sees one address space.

With `basePath` at `/`:

| Entry | Publishes |
| --- | --- |
| `['repository' => 'checkouts/core', 'mount' => '/']` | `checkouts/core/docs/v1/concepts/index.md` as `/concepts/` |
| `['repository' => 'checkouts/email', 'mount' => '/email/']` | `checkouts/email/docs/v1/index.md` as `/email/` |

The identity mount is `/`. A repository mounted at `/email/` needs no change to
its own sources, because link rewriting translates against the mounted path.

Mounts must be exclusive. Two roots at `/a/` and `/a/b/` would both claim
`/a/b/x.md`, and which one won would depend on iteration order. A file published
from one repository at an address belonging to another cannot be diagnosed from
the finished site, so the combination is refused at construction with both mounts
named.

### Target

`target` is where a repository keeps its documentation, relative to that
repository's root. It defaults to `docs/v1`, which is this repository's habit and
not a property of the package:

- a repository migrating from DocBook keeps its sources in `docs/book`
- a repository that publishes per major version keeps one directory per major
- a repository that keeps documentation at its root sets `.`

The site-level `target` is the answer for every root that states none, so a site
of a dozen repositories states it once. A repository that differs states its own:

```php
'roots' => [
    ['repository' => 'checkouts/core', 'mount' => '/'],
    ['repository' => 'checkouts/legacy', 'mount' => '/legacy/', 'target' => 'docs/book'],
],
```

A target is resolved against a repository root, never against the process's
working directory, and it cannot leave the repository it is resolved against. A
segment of `..` is refused. A name that merely contains dots, such as `v1..2`, is
not.

## The two entry shapes

| Shape | Meaning |
| --- | --- |
| `['repository' => 'checkouts/core', 'mount' => '/']` | Find this repository's documentation at its target, and refuse if it is not there |
| `['path' => 'vendor/something/docs', 'mount' => '/something/']` | Walk this directory, unresolved, for a root assembled outside a repository |

Both take `mount`. Only `repository` involves a target, because only it names
something a target can be resolved against.

An entry that cannot name a directory is dropped rather than refusing the build,
so one typo costs itself instead of the whole list. A mount that cannot prefix an
address is refused, because publishing at an address the operator did not write
would be worse than refusing to start.

## Verified, not trusted

A `repository` entry is checked before it becomes a root. A target that matches
nothing builds cleanly and publishes nothing, and that is indistinguishable from a
repository that has no pages to publish, so the build refuses instead:

| Condition | Result |
| --- | --- |
| The target directory is missing | Refused, naming the target and the directory it resolved to |
| The target directory exists but cannot be read | Refused, same message |
| The target holds no `.md` file anywhere beneath it | Refused, naming the target and the directory |

A repository with nothing to publish belongs out of the composition rather than in
it as an empty root.

## Heading ids

Every heading carries its own slug as its `id`, with no prefix:

```html
<h2 id="the-index-rule"><a href="#the-index-rule" class="heading-permalink" ...>...</a>The index rule</h2>
```

This is a contract rather than a cosmetic choice. A link to a section is
`<address>#<slug>`, and a consumer has to be able to build one from the heading it
came from. A prefix would force every consumer to learn it, so the prefix is empty and
the slug is derivable.

An authored cross-document link therefore resolves as written: `address.md#the-index-rule`
becomes `/concepts/address/#the-index-rule`, and the target page has that anchor.

The build also collects each document's headings as it parses it, as a level, a label and
a fragment. The fragment is the same value as the `id` above, so a heading found this way
is a heading a link can reach. This is what lets a consumer name a section without
building the site first and reading the HTML; it is available in process through
`CommonDoc\View\PageContext::sections()` and is not yet handed to the layout, because
nothing renders a per-page table of contents.

## Related

- [Theme and templates](theme.md) covers how a site supplies its own templates and assets
- [Documents](../concepts/document.md) defines what counts as a document
- [Addresses and files](../concepts/address.md) covers what a path becomes
- [Command reference](cli.md) lists the arguments and exit codes
