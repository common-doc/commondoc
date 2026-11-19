# Layer walkthrough: decisions, layer by layer

**Date**: 2026-11-18
**Component**: `common-doc/commondoc`
**Branch**: `tyrsson-orchestration` at `5792de1`
**Purpose**: the owner reads each layer from app entry inwards and asks questions.
This file records what is settled, what is doubtful, and what has changed. It is
not a plan and it does not authorise code.

**Why this exists**: individual components are agreed to be solid, but the
composition is not. Several layers were built to satisfy a gate or an error
message rather than to solve the problem at the layer above, and the result is a
chain of compensations. The walkthrough is the sanity check.

---

## Layer 0: app entry

### What actually happens

Read from the sources, not recalled.

| Step | File | Behaviour |
|---|---|---|
| 1 | `bin/commondoc` | anonymous function, so nothing leaks into the global namespace |
| 2 | same | resolves the Composer autoloader, `$GLOBALS['_composer_autoload_path']` first, then `../../../autoload.php`, then its own `../vendor/autoload.php` |
| 3 | same | `$projectRoot = dirname($autoload, 2) . '/'`, then `chdir($projectRoot)` |
| 4 | same | requires `$projectRoot . 'config/container.php'`; a missing file is a clean exit 1 |
| 5 | same | `$container->get(Application::class)`, `exit($application->run())` |
| 6 | `config/container.php` | requires `config/config.php`, lifts `dependencies`, registers the whole config array as the `config` service, returns `new ServiceManager($dependencies)` |
| 7 | `config/config.php` | `ConfigAggregator` over third-party providers, then this package's, then `PhpFileProvider` globs, then `development.config.php` |
| 8 | vendor | `SymfonyFactory` builds `Application(name: 'webware-console', version: '0.1.x')` and attaches a `ContainerCommandLoader` |
| 9 | vendor | `CommandLoaderFactory` merges `ConsoleInterface::class['commands']` and `laminas-cli['commands']`, refusing a duplicate name |

### Decisions this layer already fixes

**D1. The entry point belongs to the consuming project, not to the package.**
`chdir($projectRoot)` means every relative path later in the process resolves
against the *consumer's* root, not the package checkout and not the caller's
working directory. This is deliberate: the binary is meant to be run through
`vendor/bin` from a documentation repository, and that repository's `config/`
is the authority.

**D2. Configuration is one array, registered as the single `config` service.**
Nothing reads a typed config object. Every factory pulls `config` and narrows it
itself, which is why the same lookup appears in `DocumentRootFactory`,
`PageFactory`, `EnvironmentFactory` and others, each with its own fallback.

**D3. Commands are lazy.** `ContainerCommandLoader` resolves a command class only
when Symfony invokes it, so `BuildCommand` and its dependencies are not
constructed for `--help` or for any other command.

**D4. The handler is reached through a map, not through a type.** The bus
resolves `BuildSiteCommand::class` to `BuildSiteHandler::class` by reading
`config[MessageBusInterface::class]['command_map']`, then asks the container for
that class.

### Observations, one line each, no fix proposed

- `Application` reports `webware-console` and `0.1.x` for `--version`; that comes
  from the vendor factory, not from this package.
- `bin/commondoc` probes three autoloader locations; only the first is exercised
  when installed normally.

### Open questions for this layer

1. Is `chdir($projectRoot)` intended to be permanent for the process? It decides
   what every relative path later means, including `--output build`.
2. Should the site's own configuration (`basePath`, page identity) be read from
   the consumer's `config/autoload`, from a command option, or from a dedicated
   site file? This is layer 0's question because it determines what the
   container knows before any command runs.
