# commondoc

Multi-repository Markdown documentation site builder for PHP: it assembles documentation
held in several repositories into one site. It is vendor neutral and deliberately does not
live under the `webware` vendor, so it can be used by anyone.

> **The badges are fenced deliberately.** Nothing should advertise a workflow, a package or
> a report that does not exist yet: this repository has no installed workflow and no
> Packagist release. Un-fence the block below once those exist, and add a CI badge for
> whichever workflow actually runs here.

```markdown
[![PHP Version](https://img.shields.io/packagist/php-v/common-doc/commondoc)](https://packagist.org/packages/common-doc/commondoc)
[![Latest Version](https://img.shields.io/packagist/v/common-doc/commondoc)](https://packagist.org/packages/common-doc/commondoc)
[![License](https://img.shields.io/github/license/common-doc/commondoc)](LICENSE)
[![codecov](https://codecov.io/gh/common-doc/commondoc/graph/badge.svg)](https://codecov.io/gh/common-doc/commondoc)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fcommon-doc%2Fcommondoc%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/common-doc/commondoc/1.0.x)
```

## What ships here

Everything in this repository is either a **package of record** consumed from
`webware/webware-tools`, or the **thin per-repo wiring** that cannot live in a shared
config:

| Path | Role |
|---|---|
| `mago.toml` | Extends the centre (`vendor/webware/webware-tools/mago.toml`) and overrides `php-version` only. Never re-add general rules locally. |
| `bin/mago.bat` | Windows shim Infection needs to run the native Mago binary. Tracked, not ignored. No effect on Linux or CI. See webinertia/webware-tools issue 59. |
| `webware-ci.json` | The house CI workflow's parameter contract, read from the repository root. See Continuous integration below. |
| `phpunit.xml.dist` | PHPUnit 13 strict mode: `requireCoverageMetadata`, `failOnNotice`, `failOnWarning`, `failOnDeprecation`. |
| `compose.yml` / `Dockerfile` / `.devcontainer/` | The containerized toolchain (Composer, PHPUnit, Mago, Infection, PHPBench, roave BC-check). |
| `src/ConfigProvider.php` | The package wiring entry point, declared under `extra.laminas.config-provider`. |
| `.specify/` / `.github/skills/` | Spec Kit project files and the Copilot agent skills, on PowerShell scripts. |
| `.specify/presets/common-doc-standard/` | The vendored Spec Kit preset carrying this repository's constitution template, registered in `.specify/presets/.registry`. |

`mago.toml`, `bin/mago.bat`, `phpunit.xml.dist`, `.gitattributes`, `.gitignore`, `codecov.yml`, `Dockerfile`,
`.dockerignore`, `infection.json5.dist`, `phpbench.json.dist` and devcontainer config are
byte-identical to the canonical artifacts in
`webware-tools/presets/webware-alignment/artifacts/` - copy updates from there rather than
editing them here.

## Document rendering

A document renders through a real Laminas view. `CommonDoc\View\DocumentViewRenderer` is
registered for `League\CommonMark\Node\Block\Document` at priority 10, ahead of the library's own
document renderer, and it does three things: it asks the injected
`ChildNodeRendererInterface` for `renderNodes($node->children())`, hands that string to
`Mezzio\LaminasView\LaminasViewRenderer` as `documentBody`, and returns the result. The layout
wrap, every view helper and the helper state reset come from the framework, so neither
`HtmlRenderer`, `MarkdownConverter` nor `MarkdownConverterInterface` is subclassed or wrapped.

`ConfigProvider::getViewConfig()` registers:

| Template name | File | Variables |
| --- | --- | --- |
| `commondoc::document` | `templates/commondoc/document.phtml` | `documentBody` (`string`), `page` (`CommonDoc\View\Page\|null`) |
| `commondoc::layout` | `templates/commondoc/layout.phtml` | `content` (`string`), written by the view renderer |

`page` carries the `CommonDoc\View\Page` for the document being converted, put there by
`PageContextListener`, which writes `commondoc.page` into the document data before the render. A
render performed outside a build, where `PageContext` holds nothing, gets `null` and still
succeeds.

### Overriding the body template from configuration

A site replaces the body template from its own configuration. Nothing in this package changes,
no delegator is needed and no file precedence game is played:

```php
// config/autoload/templates.global.php in the site
return [
    'templates' => [
        'map' => [
            'commondoc::document' => __DIR__ . '/../templates/document.phtml',
        ],
    ],
];
```

The site template receives the same two variables and may use any Laminas view helper. Two rules
hold for it:

- It must not set `content`. That name is reserved for the layout and is written by the view
  renderer.
- It must not require a page. A no-page render still calls it with `page` set to `null`.

Removing the site entry restores the packaged template.

### Template resolution order

Resolution follows `webware-theme`, highest priority first:

| Priority | Source |
| --- | --- |
| 100 | theme resolver |
| 50 | `templates.map`, packaged or site |
| 1 | template path stack, when a consumer configures one |

The map is the deliberate override point: it is the mechanism that lets a site restyle the body
through configuration alone.

A missing template name raises the view renderer's template-not-found exception, naming the
template. It is not caught here, because a missing body template is a configuration error.

## Continuous integration

This repository has no `.github/workflows` directory. `webware-ci.json` is the parameter
contract for the required workflow hosted in `webinertia/.github`, which applies to
repositories in that organization only, so nothing reads it here today. A workflow of this
repository's own has to be added, or `webware-ci.json` removed, when that decision is taken.
No workflow file is provided by this repository today.

## Quality gates

Both MSI gates are set to **95**: the ecosystem standard, not a starting point. Lower them
only with a deliberate decision, and never silently:

```json
"min_msi": "95",
"min_covered_msi": "95"
```

Four Mago gates must be clean: `format --check`, `lint`, `analyze`, `guard`. Run `mago fmt`
first when making changes, and fix findings at source rather than adding `@mago-expect` - a
suppression needs to be a decision, not a reflex.

## Development

The toolchain runs in a container, so the host needs no PHP install. With VS Code, reopen in
the container; without it:

```shell
docker compose up -d
docker compose exec tooling composer install
docker compose exec tooling composer test
docker compose exec tooling composer test-integration
docker compose exec tooling mago lint
docker compose down
```

Packages whose tests need MySQL uncomment the `mysql` service in `compose.yml`, mirroring the
`db_image` / `db_env_json` / `db_port` values they declare in `webware-ci.json`.