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
#
# It backs the database up first and stops if that fails, and writes where
# it started from to storage/app/last-deploy-from (and deploy-history). To
# undo a deploy, see docs/ROLLBACK.md.

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

# A database backup before anything changes (audit MB-25: deploys used to
# migrate with no backup). Taken while the old code is still in place, so
# when it fails nothing has been touched and the site carries on as it was.
echo "==> Backing up the database"
if ! BACKUP_OUTPUT="$("$PHP" artisan mybooks:backup --only-db --no-cleanup 2>&1)"; then
    printf '%s\n' "$BACKUP_OUTPUT" >&2
    echo "" >&2
    echo "The backup failed, so nothing was deployed. Fix what it says above" >&2
    echo "(README, 'Backups') and run this again." >&2
    exit 1
fi
printf '%s\n' "$BACKUP_OUTPUT" | sed 's/^/    /'
BACKUP_FILE="$(printf '%s\n' "$BACKUP_OUTPUT" | sed -n 's/^Backup \([^ ]*\) .*/\1/p' | head -n 1)"
PREVIOUS_COMMIT="$(git rev-parse HEAD)"

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

# Where this deploy started from: what docs/ROLLBACK.md goes back to.
# Written only when new code came in, so running this again after a
# failed step (or with nothing new) keeps the commit and backup from
# before the deploy, not the half-deployed state. Every deploy is also
# added to storage/app/deploy-history.
if [ "$(git rev-parse HEAD)" != "$PREVIOUS_COMMIT" ]; then
    mkdir -p storage/app
    RECORD="$(printf 'commit=%s\nbackup=%s\nat=%s' "$PREVIOUS_COMMIT" "${BACKUP_FILE:-unknown}" "$(date -u +%Y-%m-%dT%H:%M:%SZ)")"
    printf '%s\n' "$RECORD" > storage/app/last-deploy-from
    printf '%s to=%s\n' "$(printf '%s' "$RECORD" | tr '\n' ' ')" "$(git rev-parse HEAD)" >> storage/app/deploy-history
fi
LAST_FROM="$(sed -n 's/^commit=//p' storage/app/last-deploy-from 2>/dev/null || true)"
LAST_BACKUP="$(sed -n 's/^backup=//p' storage/app/last-deploy-from 2>/dev/null || true)"

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

# robots.txt and sitemap.xml are written out as real files, because this
# server hands back index.php as plain text for a .txt or .xml address
# instead of running it, so the routes for them are never reached. Written
# on every deploy, so a new guide is in the sitemap as soon as it is live.
echo "==> Writing robots.txt and sitemap.xml"
# Said plainly and carried on rather than stopping the deploy here: the
# site works either way, it is only search engines that see a stale file.
"$PHP" artisan site:search-files || echo "    Could not write them, so the old files are still being served." >&2

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
    # 'git diff --quiet' says "no difference" with 0 and "there is one"
    # with 1, but it also fails with 128 when it cannot find a commit at
    # all, which is what happens on a shallow clone. Treating that as a
    # difference cried wolf about styles that had not changed, so the three
    # answers are kept apart here.
    # Asked this way so that a non-zero answer is read, not treated as the
    # script itself failing (set -e).
    DIFFED=0
    git diff --quiet "$BUILT_FROM" HEAD -- resources package.json package-lock.json tailwind.config.js vite.config.js 2>/dev/null || DIFFED=$?
    case $DIFFED in
        0)
            echo "    Nothing that affects them changed, so this is fine." >&2
            ;;
        1)
            echo "    Styles or scripts changed since then, so the site WILL look wrong." >&2
            echo "    Run 'bash scripts/package-assets.sh' on your machine and upload the zip." >&2
            ;;
        *)
            echo "    Cannot compare the two: this copy does not have ${BUILT_FROM:0:7}." >&2
            echo "    Run 'git fetch --unshallow' here if you want this check to work." >&2
            ;;
    esac
else
    echo "    Up to date."
fi

echo "==> Restarting queue workers"
"$PHP" artisan queue:restart || true

"$PHP" artisan up
echo "==> Done: $(git log -1 --format='%h %s')"
if [ "$(git rev-parse HEAD)" != "$PREVIOUS_COMMIT" ]; then
    echo "    Came from ${PREVIOUS_COMMIT:0:7}; database backup ${BACKUP_FILE:-unknown}. To undo: docs/ROLLBACK.md"
elif [ -n "$LAST_FROM" ]; then
    echo "    No new code this time. The last deploy came from ${LAST_FROM:0:7} (backup ${LAST_BACKUP:-unknown}). To undo it: docs/ROLLBACK.md"
fi
