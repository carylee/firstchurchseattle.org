#!/usr/bin/env bash
#
# GUARDRAIL: the committed maranatha-child/assets/tailwind.css must match what its
# source (assets/src/input.css) compiles to. Production serves the committed file
# and never builds, so this is what keeps the artifact from drifting away from the
# source (the exact problem that left max-w-3xl/pt-8 dead on the event page once).
#
# Run locally:  ops/scripts/check-tailwind-build.sh
set -euo pipefail
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
THEME="$REPO/wp-content/themes/maranatha-child"
cd "$THEME"

npm ci --no-audit --no-fund >/dev/null 2>&1
tmp="$(mktemp)"
npx tailwindcss -i ./assets/src/input.css -o "$tmp" --minify >/dev/null 2>&1

if diff -q "$tmp" assets/tailwind.css >/dev/null; then
  echo "tailwind.css is in sync with assets/src/input.css."
  exit 0
fi
echo "::error::assets/tailwind.css is out of sync with assets/src/input.css."
echo "  → cd wp-content/themes/maranatha-child && ./build-css.sh, then commit the result."
diff "$tmp" assets/tailwind.css | head -40 || true
exit 1
