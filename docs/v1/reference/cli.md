# Command reference

## `commondoc:build`

Build the documentation site from a directory of Markdown sources.

```
php vendor/bin/webware commondoc:build <source> [--output|-o <dir>]
```

### Arguments

| Name | Required | Meaning |
| --- | --- | --- |
| `source` | yes | Directory holding the Markdown sources |
| `output` | no | Directory the rendered site is written to. Defaults to `build`, and it is cleared before the build writes, so the output describes this build rather than every build run into it |

Both accept an absolute path. See [Getting started](../getting-started.md) for
why that matters on a machine where the entry point changes directory.

Clearing is destructive, so a path that could not be a site's output is refused rather
than emptied: an empty path, `.`, a filesystem root, or a directory that holds one of the
build's own sources. The refusal happens before anything is removed.

### Report

Each path written produces one line. Each failure produces one line naming the
path and the reason:

```
Wrote README.md
Wrote concepts/document.md
Failed concepts/paths.md: The document "concepts/paths.md" links to "nope.md", which resolves to "concepts/nope.md" and is neither a document nor an asset the scan found.
```

Paths are named as they were discovered, relative to the source root.

### Exit codes

| Code | Meaning |
| --- | --- |
| `0` | Every document and asset was written |
| `1` | At least one path was not written, or the result could not be read |

A document that fails is recorded and the build continues, so one run reports
the whole list rather than the first problem.

## Related

- [Documents](../concepts/document.md)
- [Paths and files](../concepts/paths.md)
- [Site configuration](configuration.md)
- [GitHub action](action.md) wraps this command in a container image
- The other commands this application provides come from `webware-console`, not
  from this package. Run `php vendor/bin/webware list` to see them.
