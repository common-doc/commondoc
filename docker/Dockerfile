# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Runtime image for the site builder
#
# This is NOT the development image. `../Dockerfile` builds the interactive
# toolchain environment (Mago, Xdebug, PCOV, PHPUnit) that `compose.yml`'s
# `tooling` service and the devcontainer use. This file builds the smallest
# image that can render a site: PHP, the three extensions the package
# requires, the package's runtime dependencies, and the console entry point.
#
#   docker build -f docker/Dockerfile -t commondoc:local .
#   docker run --rm commondoc:local --help
#
# This is also the image the GitHub container action in `action.yml` runs,
# once the publish workflow has pushed it to GHCR.
#
# One thing about this image is unusual and worth knowing before editing it:
# the console entry point belongs to webware-console, and it resolves the
# project root from the position of the Composer autoloader rather than from
# the working directory. `/app/vendor/autoload.php` therefore makes `/app` the
# project root, and the process changes directory to `/app` before the
# container is built. Relative paths passed on the command line are resolved
# against `/app`, not against the caller's checkout, which is why the action
# passes absolute paths.
# ---------------------------------------------------------------------------

# PHP version to base the image on. The package supports ~8.4.1 and up, and
# composer.json pins `config.platform.php` to 8.4.99, so 8.4 is the floor: the
# image then proves the package works on its lowest supported runtime.
ARG PHP_VERSION=8.4

# Composer version (copied from the official composer image at build time).
ARG COMPOSER_VERSION=2.11.1

# ---------------------------------------------------------------------------
# Composer, as a build-only source of the composer binary.
# ---------------------------------------------------------------------------
FROM composer:${COMPOSER_VERSION} AS composer

# ---------------------------------------------------------------------------
# Dependencies
#
# Built on the same PHP image as the runtime so the installed packages are
# resolved and compiled against exactly the runtime the site is rendered on.
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli AS dependencies

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libicu-dev \
        libonig-dev \
        unzip \
    ; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        intl \
        mbstring \
    ; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

# The manifests come first so a change to the source does not invalidate the
# dependency layer.
COPY composer.json composer.lock ./

RUN set -eux; \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

# The autoloader is dumped after the source arrives, and as a classmap, so the
# runtime never has to stat the filesystem to find a class.
COPY src/ src/
COPY config/ config/
COPY templates/ templates/

RUN set -eux; \
    composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------------------------------------------------------------------------
# Runtime
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli AS runtime

LABEL org.opencontainers.image.title="commondoc" \
      org.opencontainers.image.description="Builds a multi-repository Markdown documentation site." \
      org.opencontainers.image.source="https://github.com/common-doc/commondoc"

# bcmath, curl, intl and mbstring are the extensions this package needs that
# the base image does not carry: curl and mbstring are required directly, and
# intl and bcmath come in through php-standard-library/date-time and
# php-standard-library/locale (intl) and php-standard-library/math (bcmath).
# json is compiled into PHP 8 and cannot be installed separately, so it is not
# built here.
#
# libicu-dev is deliberately NOT purged. It is the source of the ICU shared
# libraries that the intl extension links against, so purging it removes
# libicu itself and intl then fails to load at startup with "libicuio.so.76:
# cannot open shared object file". Naming the runtime package instead would
# hardcode the soname, which changes with every Debian release the base image
# moves to. The cost of keeping it is the headers, which nothing here uses.
#
# git is installed because the package runs it: `Git` shells out to `git` to read a
# repository's tags, and `Checkout` clones with it, so a versioned build or an
# organization build needs the binary in the image.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        libcurl4-openssl-dev \
        libicu-dev \
        libonig-dev \
    ; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        intl \
        mbstring \
    ; \
    apt-get purge -y \
        libcurl4-openssl-dev \
        libonig-dev \
    ; \
    apt-get autoremove -y \
    ; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# The image runs as root, but a container action's caller mounts its checkout at
# /github/workspace owned by the runner's uid, so git refuses to read a repository
# there as "dubious ownership" and a versioned build silently finds no tags. The
# container is single purpose and reads only the repositories it is given, so every
# directory is trusted.
RUN git config --system --add safe.directory '*'

WORKDIR /app

COPY --from=dependencies /app/vendor ./vendor
COPY composer.json composer.lock ./
COPY src/ src/
# The package's own configuration and the two view templates are read from the
# package root at runtime, resolved through `dirname(__DIR__)`, so they belong
# in the image and not in the caller's checkout.
COPY config/ config/
COPY templates/ templates/
# The documentation ships in the image because the console cannot start without
# it. `BuildCommand` takes a `SiteConfig`, so the container builds one to list
# or run any command, and the default configuration names `docs/v1` as its
# target. Without this tree the default target resolves to nothing and every
# console invocation fails before it reaches a command, including the `list`
# the publish workflow runs. The tree doubles as the smoke build: it is a real
# site, not a fixture.
COPY docs/ docs/

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN set -eux; \
    chmod 0755 /usr/local/bin/entrypoint.sh /app/vendor/bin/webware

# No `USER`. This image is also a GitHub container action, and the runner
# mounts the calling repository at /github/workspace owned by the runner's own
# uid, which a fixed uid inside the image cannot write to. Container actions
# conventionally run as root for that reason, so the image does too.

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["--help"]
