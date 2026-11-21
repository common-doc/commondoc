# Site configuration

A build is described by one section, keyed by `CommonDoc\SiteConfig::class`, so a
deployment states its own values in its own autoload file and this package never
names one.

One key is not in that section. The base path is stated as
`view_helper_config.base_path`, because that is the value Laminas view's
`BasePath` helper answers with when a template composes a link. Reading it in one
place is what stops the paths a build writes from drifting away from the
paths a template emits. A site section that states `basePath` is refused rather
than read, and the refusal names the key that is used instead.

| Key | Meaning |
| --- | --- |
| `pathType` | `Directory` publishes `guide/setup.md` at `/guide/setup/`, served by an `index.html` inside it. `File` publishes it at `/guide/setup.html` |
| `output` | The directory a build writes to |
| `target` | Where a repository keeps its documentation, relative to that repository's root |
| `roots` | The source roots the site is composed from |
| `projectName` | The display name every page carries, or empty for none |
| `projectSlug` | The identifier every page carries, or empty for none |
| `versionLabel` | The version this build documents, or empty for none |

The base path is the applied prefix, so it is stated once at the top of the
configuration rather than in this section:

```php
return [
    'view_helper_config' => [
        // A project site publishes under its repository name.
        'base_path' => '/commondoc/',
    ],
    CommonDoc\SiteConfig::class => [
        'output' => 'build',
        'roots'  => [
            ['repository' => '.', 'mount' => '/'],
        ],
    ],
];
```


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
downstream sees one path space.

With `base_path` at `/`:

| Entry | Publishes |
| --- | --- |
| `['repository' => 'checkouts/core', 'mount' => '/']` | `checkouts/core/docs/v1/concepts/index.md` as `/concepts/` |
| `['repository' => 'checkouts/email', 'mount' => '/email/']` | `checkouts/email/docs/v1/index.md` as `/email/` |

The identity mount is `/`. A repository mounted at `/email/` needs no change to
its own sources, because link rewriting translates against the mounted path.

Mounts must be exclusive. Two roots at `/a/` and `/a/b/` would both claim
`/a/b/x.md`, and which one won would depend on iteration order. A file published
from one repository at a path belonging to another cannot be diagnosed from
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
so one typo costs itself instead of the whole list. A mount that cannot prefix a
path is refused, because publishing at a path the operator did not write
would be worse than refusing to start.

## Versions

A `repository` entry can build every released version of its documentation:

```php
['repository' => 'checkouts/message-bus', 'versions' => true],
```

Each git tag that parses as a version is read from the repository's own objects, so the
checkout is never switched, and the latest tag of each major line is published at
`/{component}/v{major}/`. The component is the repository's name. The entry's `mount` is
not read, because the version mounts are what make the versions a set.

- `/{component}/` is a page that redirects to the newest version.
- The site root is a home page with a card for each component, linking to its newest
  version and listing every version. A repository entry that is not versioned gets a card
  too, named from its mount and with no version list, unless it is mounted at the site
  root. The home page is written only when no document publishes at the root, so it never
  replaces one, and `commondoc::home` can be replaced like any other template.
- Every page's sidebar lists the released components, so a reader can move between them
  without going back to the home page.
- Every page of a versioned component lists all of its versions, so a reader can move
  between them.
- A tag that has nothing at the target is skipped, because early tags often predate the
  documentation. So is a tag whose target holds files but no Markdown document, such as one
  that kept only an earlier format: a version with nothing to read is not a version.
- Where a version has no `index.md`, the first document in sorted order is its landing page.
  The component redirect, the home page card and the version switcher all lead there, so
  none of them points at a page that does not exist.
- The `target` may be a list of directories, tried in order for each tag, so a
  repository whose layout changed between releases reads each tag from the first one it
  holds: `['docs/{major}.0', 'docs/v{major}']`.
- The `target` may contain `{major}`, which each tag replaces with its own major
  version, so `docs/v{major}` reads `docs/v1` from a 1.x tag and `docs/v2` from a 2.x
  tag. Text around the placeholder is kept, so `docs/{major}.0` reads `docs/2.0` from a
  2.x tag. Without it the same target is read from every tag. The files are written to
  `commondoc-versions` in the system temporary directory, which is cleared for each
  version before it is written.

## Organizations

An entry can name an organization instead of a repository, and every repository of it that
opts in is built as a versioned component:

```php
['organization' => 'webinertia', 'exclude' => ['docs.web-ware.net'], 'target' => ['docs/{major}.0', 'docs/v{major}']],
```

The opt-in is a GitHub **custom property** on the repository, `doc-target` by default and
set to `true`. A repository joins the site by being marked, with no edit here. Set
`property` to read a different property, and `exclude` to leave out repositories that carry
it but are not components, such as the site's own.

- The search is one request to GitHub's repository search with the `props.` qualifier.
  Archived repositories are left out. Set `GITHUB_TOKEN` to search private repositories and
  to raise the rate limit. The token is used only for the search and is never written into a
  remote URL.
- Each match is cloned with every tag into `commondoc-checkouts` in the system temporary
  directory, and brought up to date with a fetch on later builds. The clone uses whatever
  git credentials the environment already has, so a private repository is built wherever
  git can read it.
- Each checkout is then read exactly as a `repository` entry with `'versions' => true`
  is, so `target` takes the same one directory or list of candidates, and everything under
  [Versions](#versions) applies.
- GitHub returns at most a thousand search results.

PHP on Windows has no certificate bundle for cURL by default, so the search fails with an
SSL error until `curl.cainfo` points at one, for example the bundle that ships with Git:
`php -d "curl.cainfo=C:\Program Files\Git\usr\ssl\certs\ca-bundle.crt" vendor/bin/webware commondoc:build`.
The container image needs nothing.

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
`<path>#<slug>`, and a consumer has to be able to build one from the heading it
came from. A prefix would force every consumer to learn it, so the prefix is empty and
the slug is derivable.

An authored cross-document link therefore resolves as written: `paths.md#the-index-rule`
becomes `/concepts/paths/#the-index-rule`, and the target page has that anchor.

The build also collects each document's headings as it parses it, as a level, a label and
a fragment. The fragment is the same value as the `id` above, so a heading found this way
is a heading a link can reach. This is what lets a consumer name a section without
building the site first and reading the HTML; it is available in process through
`CommonDoc\View\PageContext::sections()` and is not yet handed to the layout, because
nothing renders a per-page table of contents.

## Related

- [Extensions](extensions.md) lists what loads by default and how a site adds its own
- [Theme and templates](theme.md) covers how a site supplies its own templates and assets
- [Documents](../concepts/document.md) defines what counts as a document
- [Paths and files](../concepts/paths.md) covers what a path becomes
- [Command reference](cli.md) lists the arguments and exit codes
