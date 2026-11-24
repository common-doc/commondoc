# Documents

A document is a file whose name ends in `.md`. That is the whole definition, and
it is decided in one place, so the scan and the path translation cannot
disagree about what counts.

## Discovery

The source root is walked once. Every path beneath it, relative to that root, is
read in a single pass and then partitioned:

- paths ending in `.md` are documents
- every other path is an asset

One walk serves both, so a document scan and an asset scan cannot disagree about
what exists or how a path is spelled.

The walk skips hidden entries. A `.git` directory is never part of a
documentation site, and descending into one is a source of noise at best and an
enormous file count at worst. A symbolic link to a directory is not followed, so
a link cycle cannot make a scan run forever, while a symbolic link to a file is
still published.

Paths come back sorted. Directory order differs between platforms, and a report
that reordered itself between runs would be useless to diff.

## Links between documents

A Markdown source refers to its neighbours the way a file system does, so a link
that is correct in the repository is wrong on a published site. Link targets are
therefore rewritten on the parsed tree, where a target is known, rather than on
finished HTML, where a string replacement would also rewrite prose that happens
to look like a path.

The target must resolve to something the scan found. A document that links to a
file which does not exist fails the build and names both sides of the link,
because publishing it would put a dead link on the site.

See [Paths and files](paths.md#the-index-rule) for what a target becomes,
and [Getting started](../getting-started.md) for how to run a build.
