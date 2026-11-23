# Getting started

## Requirements

- PHP 8.4 or newer, with `ext-curl`, `ext-json` and `ext-mbstring`.
- A project with a `config/container.php`, because the console entry point builds
  the container from it.

## Running a build

The entry point belongs to `webware-console`. There is no binary in this package.

```powershell
php vendor/bin/webware commondoc:build "C:\path\to\docs" --output "C:\path\to\out"
```

Both arguments accept an absolute path. `--output` defaults to `build`.

A successful run exits `0` and prints one line per path written. A run that
recorded any failure exits `1` and prints one line per failure, with the reason.

## Relative paths are project relative

The console entry point changes directory to the consuming project root before
the container is built. Every relative path in a command is therefore resolved
against that root, not against your shell's working directory.

```bash
# run from outside the project: these mean <project>/src and <project>/out
php vendor/bin/webware commondoc:build src --output out
```

Pass absolute paths when the difference matters.

## Windows

`vendor/bin/webware` is Composer's generated proxy, an extensionless file whose
first line is a `#!/usr/bin/env php` shebang. Windows has no shebang handling, so
name the interpreter explicitly:

```powershell
php vendor/bin/webware commondoc:build C:\docs C:\out
```

## Where a build writes

The output directory is created as needed. A document is written to the file its
published path implies, and an asset is copied to its own relative path. Neither
operation overwrites anything outside the output directory.

## Building this repository's own docs

```powershell
php vendor/bin/webware commondoc:build "$PWD\docs\v1" --output "$PWD\build"
```

The `assets` in that tree exist so the asset pass and the link rewriter are both
exercised by a normal build. See [Paths and files](concepts/paths.md) for how an
asset path differs from a document path.

## Previewing a build in a browser

A build is a folder of static files, so looking at one only needs a web server pointed at
it. The repository's `compose.yml` has a `preview` service for that: nginx serving `./build`
read-only, which is where `commondoc:build` writes by default.

```powershell
php vendor/bin/webware commondoc:build
docker compose up -d preview
```

Then open `http://localhost:8080/`. The files are read from disk on every request, so
rebuilding is all it takes to refresh the page. Stop it with:

```powershell
docker compose down
```

Three things to know:

- The service is bound to `127.0.0.1`, so it is reachable from your machine only. It is a
  preview, not a server to publish from.
- The site's base path has to be `/`, which is the default. A project site configured for
  `/<repository>/` has links that resolve from that path, so they will not resolve from the
  root of the preview.
- A build run inside the `tooling` container can read only what is mounted into it. A site
  composed from checkouts elsewhere on your machine is built on the host, and the preview
  serves the result the same way.

If you build to a different directory with `--output`, change the `./build` volume on the
`preview` service to match. If you delete or replace `./build` rather than rebuilding into it,
run `docker compose restart preview`: the container stays attached to the directory it started
with, so it serves nothing, and answers 404, until it is restarted.
