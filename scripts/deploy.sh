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

echo "==> Restarting queue workers"
"$PHP" artisan queue:restart || true

"$PHP" artisan up
echo "==> Done: $(git log -1 --format='%h %s')"
