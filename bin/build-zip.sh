#!/usr/bin/env bash
# Build an installable plugin ZIP (woo-free-gifts-<version>.zip) next to this repository.
# The folder inside the ZIP is always "woo-free-gifts", so WordPress updates in place.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NAME="woo-free-gifts"
VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/$NAME.php" | head -n1 | tr -d '[:space:]')"
OUT="${1:-$ROOT/../$NAME-$VERSION.zip}"
TMP="$(mktemp -d)"
mkdir -p "$TMP/$NAME"
cp -R "$ROOT/." "$TMP/$NAME/"
( cd "$TMP/$NAME" && rm -rf .git .github bin tests node_modules vendor phpcs.xml.dist .gitignore README.md CHANGELOG.md ./*.zip )
( cd "$TMP" && rm -f "$OUT" && zip -qr "$OUT" "$NAME" )
rm -rf "$TMP"
echo "Built: $OUT (version $VERSION)"
