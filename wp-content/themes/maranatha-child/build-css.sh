#!/usr/bin/env bash
#
# Compile the child theme's Tailwind source -> the committed artifact:
#   assets/src/input.css  ->  assets/tailwind.css
#
# Production NEVER runs this (HostGator has no Node); the compiled CSS is
# committed and CI verifies it stays in sync (ops/scripts/check-tailwind-build.sh).
# Run from anywhere:  ./build-css.sh            (one-off)
#                     ./build-css.sh --watch    (rebuild on change)
set -euo pipefail
cd "$(dirname "$0")"
[ -d node_modules ] || npm ci --no-audit --no-fund
exec npx tailwindcss -i ./assets/src/input.css -o ./assets/tailwind.css --minify "$@"
