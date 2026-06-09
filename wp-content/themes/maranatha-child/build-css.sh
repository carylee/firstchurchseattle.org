#!/usr/bin/env bash
#
# Compile the child theme's Tailwind source -> the built artifact:
#   assets/src/input.css  ->  assets/tailwind.css
#
# assets/tailwind.css is NOT committed (gitignored) — it's built. The CD workflow
# runs this on the deploy runner so prod serves a fresh copy; HostGator itself has
# no Node. Run it locally only when you're editing styles; otherwise `ddev
# pull-prod` fetches prod's built copy so local dev needs no Node.
# Run from anywhere:  ./build-css.sh            (one-off)
#                     ./build-css.sh --watch    (rebuild on change)
set -euo pipefail
cd "$(dirname "$0")"
[ -d node_modules ] || npm ci --no-audit --no-fund
exec npx tailwindcss -i ./assets/src/input.css -o ./assets/tailwind.css --minify "$@"
