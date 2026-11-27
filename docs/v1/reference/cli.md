# Command reference

## `commondoc:build`

Build the documentation site. Run it with no arguments to build the site the
configuration describes, which is the common case: a workflow runs this from configuration
alone, so anything it had to pass would have moved the site definition into the workflow.

```bash
php vendor/bin/webware commondoc:build [<source>] [--output|-o <dir>]
```

| Name | Required | Meaning |
| --- | --- | --- |
| `source` | no | Build this directory alone, published at `/`, instead of the configured roots. Omitting it is how a composed site is built |
| `output` | no | Write the site here instead of the configured output directory. It is cleared before the build writes, so the output describes this build rather than every build run into it |

Neither can express a mount, because a mount is part of what the site is and is read from
configuration.

Both accept an absolute path. See [Getting started](../getting-started.md) for
why that matters on a machine where the entry point changes directory.

Clearing is destructive, so a path that could not be a site's output is refused rather
than emptied: an empty path, `.`, a filesystem root, or a directory that holds one of the
build's own sources. The refusal happens before anything is removed.

A build reports as it goes: each path written produces one line, and each failure produces one
line naming the path and the reason:

```text
Wrote README.md
Wrote concepts/document.md
Failed concepts/paths.md: The document "concepts/paths.md" links to "nope.md", which resolves to "concepts/nope.md" and is neither a document nor an asset the scan found.
```

Paths are named as they were discovered, relative to the source root.

## Exit codes

| Code | Meaning |
| --- | --- |
| `0` | Every document and asset was written |
| `1` | At least one path was not written, or the result could not be read |
| `2` | The build was refused before it could start, and nothing was written |

A document that fails is recorded and the build continues, so one run reports
the whole list rather than the first problem. A refusal is the other case: the
configuration, the source or the output directory could not be used at all, so the
message says what was wrong instead of reporting pages.

## Related

- [Documents](../concepts/document.md)
- [Paths and files](../concepts/paths.md)
- [Site configuration](configuration.md)
- [GitHub action](action.md) wraps this command in a container image
- The other commands this application provides come from `webware-console`, not
  from this package. Run `php vendor/bin/webware list` to see them.
