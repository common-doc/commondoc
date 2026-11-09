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
| `mago.bat` | Windows shim Infection needs to run the native Mago binary. Tracked, not ignored. No effect on Linux or CI. See webinertia/webware-tools issue 59. |
| `webware-ci.json` | The house CI workflow's parameter contract, read from the repository root. See Continuous integration below. |
| `phpunit.xml.dist` | PHPUnit 13 strict mode: `requireCoverageMetadata`, `failOnNotice`, `failOnWarning`, `failOnDeprecation`. |
| `compose.yml` / `Dockerfile` / `.devcontainer/` | The containerized toolchain (Composer, PHPUnit, Mago, Infection, PHPBench, roave BC-check). |
| `src/ConfigProvider.php` | The package wiring entry point, declared under `extra.laminas.config-provider`. |
| `.specify/` / `.github/skills/` | Spec Kit project files and the Copilot agent skills, on PowerShell scripts. |
| `.specify/presets/common-doc-standard/` | The vendored Spec Kit preset carrying this repository's constitution template, registered in `.specify/presets/.registry`. |

`mago.toml`, `mago.bat`, `phpunit.xml.dist`, `.gitattributes`, `.gitignore`, `codecov.yml`, `Dockerfile`,
`.dockerignore`, `infection.json5.dist`, `phpbench.json.dist` and devcontainer config are
byte-identical to the canonical artifacts in
`webware-tools/presets/webware-alignment/artifacts/` - copy updates from there rather than
editing them here.

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