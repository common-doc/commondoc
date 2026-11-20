# Theme and templates

A page is rendered by Laminas view in two steps. The document template renders the
body that league/commonmark produced, and the layout wraps that render. Both are
templates, and both can be replaced by the site being built, so a deployment
changes how its pages look without editing this package and without a build flag.

Two words are kept apart throughout this page:

| Word | Means |
| --- | --- |
| Theme | The CSS, JavaScript and images a site ships |
| Template | The `.phtml` file that renders a page or its surrounding shell |

Neither is published as a document. A template is read at render time and its
output is written; the file itself never becomes a page on the site.

## What a build asks for

The renderer asks for a template by address, and these are the addresses a build
uses:

| Address | Renders |
| --- | --- |
| `commondoc::document` | One document's body |
| `commondoc::layout` | The shell wrapped around every document |

This package registers both in Laminas view's template map and sets
`templates.layout` to `commondoc::layout`, so a site with no theme configured gets
the built-in templates from `templates/default/`.

## Replacing a template

A site states its templates under the `theme` key, one map per theme name. The map
below is the sibling directory described in
[Where the built-in templates live](#where-the-built-in-templates-live):

```php
return [
    'theme' => [
        'active' => 'webware',
        'themes' => [
            'webware' => [
                'commondoc::document' => __DIR__ . '/../templates/webware/document.phtml',
                'commondoc::layout'   => __DIR__ . '/../templates/webware/layout.phtml',
            ],
        ],
    ],
];
```

`theme.active` names the theme in use. A map is keyed by the exact address the
renderer asks for, so a hit is a lookup and nothing touches the filesystem.

### Fallback is per address

The `default` theme is the fallback for every other theme, resolved address by
address rather than as a whole. With `theme.active` set to `webware`, the address
`commondoc::document` renders the first of these that exists:

1. `theme.themes.webware['commondoc::document']`
2. `theme.themes.default['commondoc::document']`
3. the template map entry this package registered

A theme that replaces the layout only, and leaves the document template alone,
gets exactly that. There is no requirement to supply both.

A theme name is a single path segment: it begins with a letter or digit and the rest
is letters, digits, hyphen and underscore. A name containing a slash or a dot, or one
beginning with a hyphen or underscore, is refused when the helper is built.

## Ordering the resolver

The theme is consulted before the template map, which is what lets a site's file
win over the identical address this package registered. That ordering is declared
by the theme package rather than left to the order of a provider list, so a site
does not have to arrange its own `config.php` to get it. It does require that
config is aggregated by a manager that understands provider ordering, such as
`webware/webware-configmanager`, or by one that happens to merge the theme
provider last.

## Assets

Asset names are declared per theme, and the helper turns a relative value into an
address under the theme that defined it:

```php
'theme' => [
    'active' => 'webware',
    'assets' => [
        'default' => [
            'theme.css' => 'css/theme.css',
        ],
        'webware' => [
            'theme.css'  => 'css/theme.css',
            'theme.js'   => 'js/theme.js',
            'bootstrap'  => 'https://cdn.example/bootstrap.min.css',
        ],
    ],
],
```

With `webware` active, measured in a build:

| Call | Returns |
| --- | --- |
| `$this->asset('theme.css')` | `/theme/webware/css/theme.css` |
| `$this->asset('theme.js')` | `/theme/webware/js/theme.js` |
| `$this->asset('bootstrap')` | `https://cdn.example/bootstrap.min.css` |

The `default` theme's values are laid in first and the active theme's over them, so
a name the active theme does not declare is served from `default`, and the address
names `default` because that is the theme that defined it.

A value beginning with `https://`, `http://` or `//` is passed through unchanged.
Anything else is treated as a path inside the theme and gets the `/theme/<theme>/`
prefix.

Names already in Laminas view's own `view_helper_config.asset.resource_map` are the
base of the merged map, so a site that already declares assets there keeps working.

### Publishing the files

The helper returns an address; it does not copy anything. For
`/theme/webware/css/theme.css` to resolve on the published site, the file has to be
published at that address. It already is, provided the theme directory sits inside
the site's own source root:

```
theme/
  webware/
    css/theme.css
```

A build of that root, measured:

```
Wrote theme/webware/css/theme.css
```

Only `.md` files become documents, so the theme's CSS, JavaScript and images are
copied unchanged by the ordinary asset pass, at the address the helper emits. There
is nothing to configure for this and no second root to add: a root mounted at
`/theme/` alongside a root at `/` is refused as overlapping, and it would say the
same thing twice anyway. A theme directory belongs in the repository that builds the
site, not in a documented repository it composes.

### Base path

The asset helper applies no base path. It returns the value from the map verbatim,
so a site served under a prefix composes the two in its template:

```php
<link rel="stylesheet" href="<?= $this->basePath() . $this->asset('theme.css') ?>">
```

`basePath()` reads `view_helper_config.base_path` and returns it with a trailing
slash removed, so `/commondoc/` and `/commondoc` are the same value. That is also
where the build reads its address space, so the site states the prefix once and both
sides follow it. Measured: setting `base_path` to `/commondoc/` publishes a page at
`/commondoc/getting-started/` and makes `basePath()` return `/commondoc`, which
composes with an asset address without a doubled or missing slash.

## What the templates receive

Both templates are ordinary Laminas view templates, rendered with strict variables
on, so a template that reads a variable it was not given fails the build instead of
rendering an empty string.

`commondoc::document` is rendered with exactly two variables:

| Variable | Type | Contents |
| --- | --- | --- |
| `documentBody` | `string` | The document rendered to HTML, which is what a template echoes |
| `page` | `CommonDoc\View\Page` or `null` | The page this document belongs to, or `null` when the render is not part of a build |

Every field of `Page` is public and readable: `label`, `projectName`, `projectSlug`,
`sourcePath`, `url`, `versionLabel`, `append`, `captureTo`, `children`, `template`
and `terminal`.

`page` is null in a preview render and set in a build, so a template reads it behind
a null check rather than assuming it:

```php
<?php if (null !== $page) : ?>
    <?php $this->headTitle($page->label); ?>
<?php endif ?>
<main class="document">
    <?= $documentBody ?>
</main>
```

`commondoc::layout` receives one variable, `content`, written by the view renderer.
It receives nothing else, and it is rendered for any page the renderer wraps, so it
must not assume a page exists.

## Where the built-in templates live

This package ships `templates/default/`, holding `document.phtml` and `layout.phtml`.
The directory is named for the theme the resolver falls back to, so a site's own
theme directory is a sibling:

```
templates/
  default/
    document.phtml
    layout.phtml
  webware/
    document.phtml
    layout.phtml
```

A site does not edit anything under `default/`. It adds its own directory beside it
and states that directory in `theme.themes`, which is why the two can be upgraded
independently.

## Not settled yet

These are known gaps rather than behaviour to rely on:

- The built-in templates render a bare page with a title and no stylesheet. The
  default is intended to be a styled theme in its own right.
- There is no landing page template. A composed site's root page is a document like
  any other, and what a landing page receives is undecided.
- A per-page table of contents is not rendered. The headings are collected and
  available in process, but nothing passes them to a template.

## Related

- [Site configuration](configuration.md) covers composition, mounts and targets
- [Documents](../concepts/document.md) defines what counts as a document
- [Addresses and files](../concepts/address.md) covers what a path becomes
