#!/usr/bin/env bash
# Build the WordPress.org release zip: build/conflict-radar-finder.zip with a
# single top-level conflict-radar-finder/ folder, production autoloader
# included, dev files excluded via ../.distignore.
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"

VERSION="$(grep -m1 '^\s*\*\s*Version:' conflict-radar.php | awk '{print $NF}')"
CONST_VERSION="$(grep -m1 "CONFLICT_RADAR_VERSION'," conflict-radar.php | sed -E "s/.*'([0-9.]+)'.*/\1/")"
if [ "$VERSION" != "$CONST_VERSION" ]; then
	echo "Version mismatch: header says $VERSION, constant says $CONST_VERSION" >&2
	exit 1
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
STAGE="$WORK/conflict-radar-finder"
mkdir -p "$STAGE"

rsync -a --exclude-from=.distignore ./ "$STAGE"/

# Production autoloader (no dev dependencies ship). If Composer is not
# available the zip still works — the bootstrap has a PSR-4 fallback —
# but say so loudly so the omission is a decision, not an accident.
if command -v composer >/dev/null 2>&1; then
	# .distignore keeps the Composer files out of the zip, but the
	# install needs them — lend them to the stage, take them back after.
	# The staged vendor/ is deleted first: a fresh --no-dev install is
	# the only guarantee no dev package leaks into the release.
	cp composer.json composer.lock "$STAGE"/
	rm -rf "$STAGE"/vendor
	( cd "$STAGE" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction )
else
	echo "WARNING: composer not found; shipping without vendor/ (fallback autoloader will be used)" >&2
fi
rm -f "$STAGE"/composer.lock

mkdir -p "$ROOT/build"
OUT="$ROOT/build/conflict-radar-finder.zip"
rm -f "$OUT"
( cd "$WORK" && zip -qr "$OUT" conflict-radar-finder )

echo "Built $OUT (version $VERSION)"
unzip -l "$OUT" | tail -5
