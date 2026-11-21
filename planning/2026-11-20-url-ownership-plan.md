# URL ownership: the seam plan

Date: 2026-11-20

This is the plan that answers "can this be fixed without starting over". Nothing in this
document has been implemented. It is written down because the same reasoning was produced
three times and lost each time.

## The answer

Nothing here needs a rewrite, and the reason is measurable: the seams already exist and the
machinery already resolves.

Verified by probing the real container:

```
router        : Webware\Router\QueryParamRouter
generateUri   : true
url helper    : true
```

`RouterInterface` already aliases to `QueryParamRouter` at `src/ConfigProvider.php:206`, and
`UrlHelper` is already in the container because `mezzio-helpers` is aggregated at
`config/config.php:47`. So the fix needs **no new dependency**. Not even navigation:
commondoc's job is to register routes *with metadata*, and if a pages repo uses navigation in
its templates, it consumes what we registered.

## The blast radius

`src` holds 62 files. The affected set is seven of them, plus three new small types.

| File | Change | Risk |
| --- | --- | --- |
| `Container/PageFactory.php` | one line: URL from the resolver, not from `DocumentRoot` | low, one producer |
| `CommandHandler/BuildSiteHandler.php` | `seed()` gains route options | additive |
| `Output/DocumentRoot.php` | `published()` narrows to registration duty | medium, three call sites |
| `Output/LinkRewriter.php` | resolve through route name plus `generateUri` | medium, internals only |
| `Container/MarkdownConverterFactory.php` | pass the resolver | low |
| `Container/SiteConfigFactory.php` | per-root identity becomes possible | low |
| `ConfigProvider.php` | wire the resolver | low |

New types, all small:

- component identity: one repository name in, `/message-bus` and `Message Bus` out
- a URL resolver
- a middleware that scopes the url helper to one document

## What is untouched

`Scan`, `Root`, `Target`, `Tree`, `Asset`, `Markdown`, `Source`, `SourceReader`,
`SourceLinks`, `SourcePath`, `PathType`, `HeadingListener`, `PageContext`, the whole
`Record\` namespace, `Console\`, `Http\`, `Listener\`, `Middleware\AssetCopy`,
`Middleware\SourceLoading`, `DocumentViewRenderer`, and all of `templates\`.

`Page::$url` has exactly one producer, `Container/PageFactory.php:103`, and one consumer
contract, `Page::toArray()`. That is why this is surgical rather than viral.

## Why the three restarts happened

Not because the target moved. Because each restart rebuilt the *layer* rather than the
*seam*. The pipeline, scan, target resolution and record types have been correct since before
the first restart. What was wrong was that URL composition lived in `DocumentRoot`, and the
proposed fix was to add a version-expansion layer into `Scan` rather than moving one
responsibility out of one class.

## Order, green at every step

1. **Component identity.** A new type: one repository name in, `/message-bus` and
   `Message Bus` out. Pure function, tests only, nothing wired. Zero risk.

2. **Route options.** `seed()` attaches `navigation`, `label`, `parent` and `order`.
   Additive; nothing reads them yet. A test asserts they land.

3. **Registration owns the path.** Composition moves into the route writer; `published()`
   delegates to it. Behaviour identical; `DocumentRootTest` and `BuildSiteHandlerTest`
   adjust in place.

4. **Resolution through the router.** `Page::$url` and `LinkRewriter` call `generateUri`.
   `published()` loses its last consumer outside registration. This is the step that
   deletes the duplication.

5. **Versions.** Child routes sharing the base route, with `{version}` as a substitution.
   Now it is a registration change, not a document-model change.

6. **Url helper wiring and docs.** Middleware scoping per document, template guidance,
   `configuration.md`.

Steps 1 and 2 are additive and reversible and neither touches `DocumentRoot`,
`LinkRewriter` or any template, so they cannot regress the corpus build.

Step 4 is the one real behavioural change, and it is where the current test suite earns its
keep: the corpus build either produces identical output or it does not.

## Recorded evidence behind the plan

- `QueryParamRouter::replaceSubstitution` is eight lines and does exactly what the withdrawn
  version-expansion design was invented to avoid:
  `str_replace("{{$key}}", (string) $value, $path)`.
- `NavigationItem::getHref()` returns `$route->getPath()` with the fragment appended; the
  tree is assembled from `parent` options in `Navigation.php`.
- `NavigationFilterIterator` requires `options['navigation']`; `seed()` sets no options at
  all, so the navigation tree can never render anything from a commondoc build.
- `d7eb803` added fragment support to `webware-navigation` (PR #40), and commondoc then
  derived heading fragments independently in `HeadingListener`, so the same concept exists on
  both sides with no connection between them.
- `SiteConfig::$projectName` and `SiteConfig::$projectSlug` are hand-typed site-wide scalars
  with no relation to each other or to any repository, so nothing can detect them disagreeing.
