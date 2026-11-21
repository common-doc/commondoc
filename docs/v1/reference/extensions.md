# Extensions

CommonMark converts one document at a time and knows nothing else. Everything a
site needs beyond that, such as tables, a table of contents or heading permalinks,
is an extension, and a build is the set of extensions it loads.

Two kinds are documented apart, because they are supported differently:

| Kind | Where it lives | Who maintains it |
| --- | --- | --- |
| Bundled | `league/commonmark` itself | The CommonMark project |
| Extension package | Its own repository, installed by the site | This project, per package |

Nothing custom to commondoc replaces a bundled extension. Where commondoc needs
different behaviour it configures the bundled extension, which is what the current
set does.

## Enabled by default

These load on every build whether or not anything registers an extension. The order
below is the load order and is part of the contract, because an extension that
depends on another extension's parse step has to be added after it:

| Order | Extension | What it adds |
| --- | --- | --- |
| 1 | `TableExtension` | Pipe tables, with alignment |
| 2 | `StrikethroughExtension` | `~~struck~~` |
| 3 | `TaskListExtension` | `- [ ]` and `- [x]` list items |
| 4 | `HeadingPermalinkExtension` | A link before every heading, and the heading's `id` |
| 5 | `TableOfContentsExtension` | A contents list at the top of a document |
| 6 | `NormalizeHeadingsExtension` | Heading levels rebased to a consistent start |
| 7 | `ExternalLinkExtension` | `rel` attributes on links that leave the site |
| 8 | `AutolinkExtension` | Bare URLs become links |
| 9 | `DisallowedRawHtmlExtension` | The GFM tagfilter list |

`CommonMarkCoreExtension` is added before all nine and is not configurable.

Every one of these ships with `league/commonmark`, so enabling them costs no
dependency. Their configuration lives under the extension's own key inside the
`League\CommonMark\Environment\EnvironmentInterface` section, and each key is
documented with its defaults in `config/autoload/commonmark.global.php`.

## Admonitions

Callouts are written as GitHub alerts, so the same file reads as a blockquote on GitHub and as a styled callout here:

```markdown
> [!WARNING]
> Back up the database before you run the migration.
```

> [!NOTE]
> This block is itself an admonition, which is how the build checks that the extension is loaded.

The five kinds are `NOTE`, `TIP`, `IMPORTANT`, `WARNING` and `CAUTION`, and the marker is read without regard to case. Each renders as a Bootstrap alert with a title and an icon, in the markup [the default theme](theme.md) styles. The block holds anything a blockquote can, including lists and code.

The marker has to be alone on the first line. `> [!NOTE] some text` is an ordinary blockquote, as it is on GitHub, and so is a quotation that merely mentions a marker. A marker that names no kind, such as `[!DANGER]`, is left alone.

The extension ships in this package and is registered the way any extension package would register itself, through the plugin manager's section of the configuration. It is `league/commonmark`-shaped, a node, a listener and a renderer with no configuration and no collaborators, so it can be moved to a package of its own without changing what it does.

## Extension packages

An extension that is not bundled lives in its own repository. It is a Composer
package with a `ConfigProvider`, and installing it is the entire registration step:

```php
namespace Your\Extension;

use League\CommonMark\Extension\ExtensionInterface;

final readonly class ConfigProvider
{
    /**
     * @return array<string, array{factories: array<class-string, class-string>}>
     */
    public function __invoke(): array
    {
        return [
            'CommonDoc\Container\ExtensionPluginManager' => [
                'factories' => [
                    AdmonitionExtension::class => AdmonitionExtensionFactory::class,
                ],
            ],
        ];
    }
}
```

Three things make that enough:

- The plugin manager's config section is keyed by the plugin manager's own class name,
  so every package contributes to one section and this package never names an
  extension.
- A `factory` is resolved through the container, so an extension that needs
  configuration or a collaborator reads `$container->get('config')` the ordinary way.
  An extension with no dependencies can use `invokables` instead and skip the factory.
- The manager is a single-instance plugin manager, which is what CommonMark expects:
  one extension object per name, holding no per-document state.

### Load order

The nine bundled extensions load first, then whatever the site's config section
declares. Within the declared set, `invokables` load before `factories`, and within
each the order is the order the keys were added.

A package that has to run before a bundled extension cannot state that through
configuration. Arranging it means contributing through `factories` and being aware
that the bundled set still comes first; if that is genuinely required, it is a change
to this package rather than to a site's configuration.

### Versioning

Each extension package is versioned and released independently, so it can track a
commondoc release rather than move in lockstep with it. The constraint a site writes
is on the extension package, not on this one.

## Planned

Nothing is planned that is not built. Extensions that are wanted but undecided are tracked in the repository's issues rather than here.

## Related

- [Theme and templates](theme.md) covers how a site supplies its own templates
- [Site configuration](configuration.md) covers composition, mounts and targets
