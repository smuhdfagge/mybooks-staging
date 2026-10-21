#!/usr/bin/env bash
#
# Deploy the latest main on the server, in the right order, in one go.
#
#   bash scripts/deploy.sh
#
# Hostinger's command line runs PHP 8.2 by default, but MyBooks needs 8.4,
# so PHP 8.4 is called by its full path. Override if yours differs:
#   PHP=/path/to/php84 COMPOSER_BIN=/path/to/composer bash scripts/deploy.sh
#
# The front-end files (public/build) are not in git and are not built here
# (no Node on the server): upload the build zip separately when one is sent.

set -euo pipefail

PHP="${PHP:-/opt/alt/php84/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"

cd "$(dirname "$0")/.."

if [ ! -x "$PHP" ]; then
    echo "PHP 8.4 not found at $PHP. Set PHP=/path/to/php84 and try again." >&2
    exit 1
fi
if [ -z "$COMPOSER_BIN" ]; then
    echo "Composer not found. Set COMPOSER_BIN=/path/to/composer and try again." >&2
    exit 1
fi

echo "==> Getting the latest code"
# Never stop at a username/password prompt: GitHub no longer accepts
# account passwords, so a prompt here can only fail. Fail with a clear
# message instead (see "Server access to GitHub" in the README).
export GIT_TERMINAL_PROMPT=0
if ! git pull --ff-only origin main; then
    echo "" >&2
    echo "Could not get the latest code from GitHub." >&2
    echo "  - If it said 'could not read Username' or asked for a password, the" >&2
    echo "    server has no access key: add a deploy key (README, 'Server access" >&2
    echo "    to GitHub') and run: git remote set-url origin git@github.com:$(git remote get-url origin | sed -E 's#(https://github.com/|git@github.com:)##')" >&2
    echo "  - If it said 'Not possible to fast-forward', files were changed on the" >&2
    echo "    server: run 'git status' to see them." >&2
    exit 1
fi

echo "==> Installing PHP packages"
"$PHP" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

echo "==> Maintenance mode on"
"$PHP" artisan down --retry=30 || true
# Bring the site back up even if a step below fails.
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Database changes"
"$PHP" artisan migrate --force

echo "==> Refreshing cached routes, config and views"
# A stale route cache is what causes "Route [...] not defined" after an update.
"$PHP" artisan optimize:clear
"$PHP" artisan optimize

# The front-end files are built elsewhere (no Node here), so they can be
# left behind by an update that changed a stylesheet or a view. When that
# happens the site still works but looks wrong, which is hard to spot and
# easy to blame on the page itself. Say so instead.
echo "==> Checking the front-end files"
BUILT_FROM="$(cat public/build/BUILD_COMMIT 2>/dev/null || true)"
HEAD_COMMIT="$(git rev-parse HEAD)"
if [ ! -d public/build ]; then
    echo "    public/build is missing: the site will have no styling." >&2
    echo "    Run 'bash scripts/package-assets.sh' on your machine and upload the zip." >&2
elif [ -z "$BUILT_FROM" ]; then
    echo "    Cannot tell which commit these files were built from." >&2
    echo "    If anything looks unstyled, upload a fresh build (scripts/package-assets.sh)." >&2
elif [ "$BUILT_FROM" != "$HEAD_COMMIT" ]; then
    echo "    These were built from ${BUILT_FROM:0:7}, but the code is now $(git rev-parse --short HEAD)." >&2
    if ! git diff --quiet "$BUILT_FROM" HEAD -- resources package.json package-lock.json tailwind.config.js vite.config.js 2>/dev/null; then
        echo "    Styles or scripts changed since then, so the site WILL look wrong." >&2
        echo "    Run 'bash scripts/package-assets.sh' on your machine and upload the zip." >&2
    else
        echo "    Nothing that affects them changed, so this is fine." >&2
    fi
else
    echo "    Up to date."
fi

echo "==> Restarting queue workers"
"$PHP" artisan queue:restart || true

"$PHP" artisan up
echo "==> Done: $(git log -1 --format='%h %s')"
