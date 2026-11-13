# Contract: Template registry

**Type**: Public surface. A site depends on these names and on these variables.

## Registered by `ConfigProvider::getViewConfig()`

```php
return [
    'templates' => [
        'map' => [
            'commondoc::document' => __DIR__ . '/../templates/commondoc/document.phtml',
            'commondoc::layout' => __DIR__ . '/../templates/commondoc/layout.phtml',
        ],
        'layout' => 'commondoc::layout',
    ],
];
```

The retired `view_manager.template_path_stack` key is not emitted.

## `commondoc::document` (body)

| Variable | Type | Required | Notes |
|---|---|---|---|
| `documentBody` | `string` | yes | The rendered body, produced by CommonMark |
| `page` | `Page|null` | yes | Null during a render with no page |

**Rules**

- The template must not set `content`. `content` is reserved for the layout and is written by the
  view renderer.
- The template must not require a page. Helper output must work with only `documentBody`.
- The template may use any Laminas view helper.

## `commondoc::layout` (shell)

| Variable | Type | Required | Notes |
|---|---|---|---|
| `content` | `string` | yes | Written by the view renderer from the body render |

**Rules**

- The layout is named once in `templates.layout`; it is not resolved per render.
- The layout must not assume a page, because the no-page render still wraps in it.

## Override precedence

A site replaces a template without touching this package:

| Priority | Source |
|---|---|
| 100 | theme resolver (`webware-theme`) |
| 50 | template map (this package's entries, or a site's entries in the same map) |
| 1 | template path stack, when a consumer configures one |

The map is the intended override mechanism. A site that wants a different document body adds or
replaces the `commondoc::document` entry in its own `templates.map` configuration. No code change,
no delegator and no file precedence game is required.

## Failure behaviour

- A missing template name raises the view renderer's template-not-found exception, which names the
  template. The renderer does not catch it, because a missing body template is a configuration
  error, not a rendering failure to paper over.
- A missing page raises nothing. The render succeeds with `page` null.
