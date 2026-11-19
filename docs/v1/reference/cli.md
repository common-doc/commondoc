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
| `output` | no | Directory the rendered site is written to. Defaults to `build` |

Both accept an absolute path. See [Getting started](../getting-started.md) for
why that matters on a machine where the entry point changes directory.

### Report

Each path written produces one line. Each failure produces one line naming the
path and the reason:

```
Wrote index.md
Wrote concepts/document.md
Failed concepts/address.md: The document "concepts/address.md" links to "nope.md", which resolves to "concepts/nope.md" and is neither a document nor an asset the scan found.
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
- [Addresses and files](../concepts/address.md)
- The other commands this application provides come from `webware-console`, not
  from this package. Run `php vendor/bin/webware list` to see them.
