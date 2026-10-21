#!/usr/bin/env bash
#
# Build the front-end files and pack them for the server.
#
#   bash scripts/package-assets.sh
#
# The server has no Node, so public/build is built here and uploaded as a
# zip. The zip records the commit it was built from, which is what lets
# deploy.sh say so when the server's files are from a different one.
#
# Unzip it on the server over public/ so that public/build is replaced.

set -euo pipefail

cd "$(dirname "$0")/.."

if ! command -v npm >/dev/null 2>&1; then
    echo "npm not found. Node 20+ is needed to build the front-end files." >&2
    exit 1
fi

COMMIT="$(git rev-parse HEAD)"
SHORT="$(git rev-parse --short HEAD)"

if [ -n "$(git status --porcelain)" ]; then
    echo "Note: there are uncommitted changes, so this build is not exactly $SHORT." >&2
fi

echo "==> Building"
npm ci
npm run build

# What deploy.sh reads to tell whether the server's files match its code.
echo "$COMMIT" > public/build/BUILD_COMMIT

OUT="build-$SHORT.zip"
rm -f "$OUT"
echo "==> Packing $OUT"
zip -qr "$OUT" public/build

echo "==> Done: $OUT ($(du -h "$OUT" | cut -f1))"
echo "    Upload it to the server and unzip it there, over the project folder:"
echo "      unzip -o $OUT"
