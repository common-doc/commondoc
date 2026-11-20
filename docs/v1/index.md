# commondoc

commondoc turns a directory of Markdown files into a static documentation site.
It reads Markdown, converts it to HTML, renders that HTML through Laminas view,
and writes the result to local files that can be published anywhere.

The design keeps one boundary in view at all times: league/commonmark converts
one document at a time, and it knows nothing about files, paths or sites. A build
is therefore a sequence of passes around a one-document engine, not a single
conversion.

## What a build does

1. Walk the source root and read every path beneath it.
2. Partition those paths into documents and assets. A document is a Markdown
   file. Everything else is an asset, copied unchanged.
3. Register an address for every document, so a link to a document the build has
   not reached yet still resolves.
4. Convert each document and write it to the output directory.
5. Copy each asset to the same relative path in the output directory.

## Where to go next

- [Getting started](getting-started.md) explains how to run a build.
- [Concepts](concepts/index.md) covers the two ideas the whole tool rests on.
- [Site configuration](reference/configuration.md) covers composition, mounts and targets.
- [Theme and templates](reference/theme.md) covers how a site supplies its own
  templates and assets.
- [Extensions](reference/extensions.md) lists what loads by default and how a site
  adds its own.
- [Command reference](reference/cli.md) lists the arguments and exit codes.
- [GitHub action](reference/action.md) builds the site from a workflow with no
  toolchain on the runner.

A build that runs cleanly reports every path it wrote, or why it did not:

```
Wrote index.md
Wrote guide/setup.md
Failed guide/broken.md: The document "guide/broken.md" links to "missing.md", which resolves to "guide/missing.md" and is neither a document nor an asset the scan found.
```

That failure is deliberate. A link to a file the scan did not find is a broken
link on the published site, so the build names it rather than publishing it.

## Layout of a source tree

```
docs/v1/
  index.md            -> /
  getting-started.md  -> /getting-started/
  concepts/
    index.md          -> /concepts/
    document.md       -> /concepts/document/
  assets/
    site.css          -> /assets/site.css
```

The rule that produces the left column from the right is described in
[Addresses and files](concepts/address.md).
