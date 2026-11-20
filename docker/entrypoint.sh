#!/bin/sh
# ---------------------------------------------------------------------------
# Container entrypoint for the site builder.
#
# The image runs the package's own console entry point, which has exactly one
# command: `commondoc:build`. There is no verb layer here for that reason. A
# small set of arguments belongs to the console rather than to that command, and
# everything else, including nothing at all, is a `commondoc:build` argument
# list:
#
#   --version, -V, --help, -h, --ansi, --no-ansi
#   --quiet, -q, --no-interaction, -n
#   --verbose, -v, -vv, -vvv
#   list, help, completion, menu
#
# With no arguments it builds the site the configuration describes, because that
# is what `commondoc:build` does with nothing to override. The image's own CMD
# asks for `--help`, so `docker run <image>` prints the console's help while
# `docker run --entrypoint /usr/local/bin/entrypoint.sh <image>` builds.
#
# The console options are named rather than inferred from "begins with a dash".
# The action passes the build command its arguments positionally, and an empty
# `source` either arrives as an empty argument or is dropped by the runner. In
# the dropped case the first argument is `--output`, which is not a console
# option, so a bare "begins with a dash" rule would hand a build to the console
# and fail looking for a command that does not exist.
#
# The console entry point changes directory to the project root it derives from
# the autoloader, which inside this image is `/app`. A relative path therefore
# means something under `/app`, not something under the caller's checkout, so
# pass absolute paths. The GitHub action does exactly that.
#
# One environment variable is honoured:
#   COMMONDOC_CONFIG   path to a PHP file to install as
#                      `/app/config/autoload/commondoc.global.php`, which is how
#                      a composed site supplies its own root list, target, mount
#                      points, base path, and output directory. It is read
#                      through the environment rather than through an argument
#                      because `commondoc:build` has no `--config` option:
#                      configuration is aggregated before any command runs, so
#                      the file has to be in place first.
#
# A COMMONDOC_CONFIG that is set but unreadable is refused rather than ignored.
# A workflow that named a file and got a build of the image's own documentation
# instead would look like a successful run of the wrong site.
# ---------------------------------------------------------------------------
set -eu

BIN="${COMMONDOC_BIN:-/app/vendor/bin/webware}"
COMMAND="commondoc:build"
CONFIG_TARGET="/app/config/autoload/commondoc.global.php"

if [ -n "${COMMONDOC_CONFIG:-}" ]; then
    if [ ! -f "$COMMONDOC_CONFIG" ]; then
        echo "entrypoint: COMMONDOC_CONFIG is set to \"$COMMONDOC_CONFIG\", which is not a readable file" >&2
        exit 2
    fi

    cp "$COMMONDOC_CONFIG" "$CONFIG_TARGET"
fi

if [ "$#" -eq 0 ]; then
    exec "$BIN" "$COMMAND"
fi

case "$1" in
    --version | -V | --help | -h | --ansi | --no-ansi | --quiet | -q | --no-interaction | -n | --verbose | -v | -vv | -vvv | list | help | completion | menu)
        exec "$BIN" "$@"
        ;;
    *)
        exec "$BIN" "$COMMAND" "$@"
        ;;
esac
