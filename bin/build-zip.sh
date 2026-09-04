#!/usr/bin/env bash
# Build an installable plugin ZIP (woo-free-gifts.zip) next to this repository.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NAME="woo-free-gifts"
OUT="$ROOT/../$NAME.zip"
TMP="$(mktemp -d)"
mkdir -p "$TMP/$NAME"
rsync -a "$ROOT/" "$TMP/$NAME/" \
	--exclude .git --exclude .github --exclude bin --exclude node_modules --exclude vendor \
	--exclude phpcs.xml.dist --exclude tests --exclude .gitignore --exclude '*.zip' --exclude README.md
( cd "$TMP" && rm -f "$OUT" && zip -qr "$OUT" "$NAME" )
rm -rf "$TMP"
echo "Built: $OUT"
