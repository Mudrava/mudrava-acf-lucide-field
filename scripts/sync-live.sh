#!/usr/bin/env bash
# Mirror production files into a staging tree and point the local test site at
# it. The site then evaluates exactly what ships in the dist archive, so admin
# runtime, BrowserKit and Plugin Check all see production-only files.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="$(basename "$REPO")"
LIVE_DIR="${MUDRAVA_LIVE_DIR:-${TMPDIR:-/tmp}/mudrava-acf-live}"
SITE_DIR="${1:-${MUDRAVA_SITE_DIR:-}}"

mkdir -p "$LIVE_DIR/$SLUG"
rsync -a --delete --exclude-from="$REPO/.distignore" "$REPO/" "$LIVE_DIR/$SLUG/"

if [ -n "$SITE_DIR" ]; then
	rm -f "$SITE_DIR/wp-content/plugins/$SLUG"
	ln -sfn "$LIVE_DIR/$SLUG" "$SITE_DIR/wp-content/plugins/$SLUG"
fi

echo "$LIVE_DIR/$SLUG"
