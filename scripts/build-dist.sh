#!/usr/bin/env bash
# Build a WordPress.org-ready distribution archive excluding dev-only files.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="$(basename "$REPO")"
OUT_DIR="$REPO/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/$SLUG" "$OUT_DIR"

rsync -a --exclude-from="$REPO/.distignore" "$REPO/" "$STAGE/$SLUG/"

rm -f "$OUT_DIR/$SLUG.zip"
( cd "$STAGE" && zip -qr "$OUT_DIR/$SLUG.zip" "$SLUG" -x '*.DS_Store' )

echo "$OUT_DIR/$SLUG.zip"
