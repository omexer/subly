#!/usr/bin/env bash
# Build the distributable zip. Source-only files (dev harnesses, git metadata,
# WordPress.org assets) never ship, which is also why Plugin Check must be run
# against the built output rather than the working tree.
set -euo pipefail

PLUGIN="subkit-subscriptions"
OUT="${1:-$PWD/dist}"
SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

rm -rf "$OUT/$PLUGIN" && mkdir -p "$OUT/$PLUGIN"

rsync -a --exclude='.git' --exclude='.gitignore' --exclude='.wordpress-org' \
        --exclude='tools' --exclude='dist' --exclude='.DS_Store' \
        "$SRC/" "$OUT/$PLUGIN/"

( cd "$OUT" && rm -f "$PLUGIN.zip" && zip -rq "$PLUGIN.zip" "$PLUGIN" )
echo "built: $OUT/$PLUGIN.zip"
