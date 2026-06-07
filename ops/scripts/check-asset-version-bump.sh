#!/usr/bin/env bash
#
# CI guard: if a change touches a plugin/theme's front-end assets (*.js / *.css),
# it must ALSO bump that plugin/theme's version in the same change.
#
# Why: our admin/theme assets are enqueued with the plugin version as the
# cache-buster (e.g. admin.js?ver=0.2.0). Shipping new JS/CSS without bumping the
# version leaves browsers — and Cloudflare, in front of the site — serving the
# stale file under the old URL. This is exactly the stock-photos 0.1.0 incident
# (the preview lightbox didn't appear because admin.js was cached). This guard
# makes that mistake fail CI instead of shipping silently.
#
# Diff is computed against BASE_REF (default origin/main). On a shallow CI
# checkout, fetch the base first (actions/checkout fetch-depth: 0).
set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$REPO"

BASE="${BASE_REF:-origin/main}"

# Third-party vendored dirs we don't own (don't require a version bump for them).
EXEMPT="wp-content/themes/maranatha"

is_exempt() {
  local dir="$1"
  for e in $EXEMPT; do
    [ "$dir" = "$e" ] && return 0
  done
  return 1
}

if ! git rev-parse --verify "$BASE" >/dev/null 2>&1; then
  echo "asset-version-bump: base ref '$BASE' not found; skipping (nothing to diff against)."
  exit 0
fi

MB="$(git merge-base "$BASE" HEAD 2>/dev/null || echo "$BASE")"
mapfile -t changed < <(git diff --name-only "$MB" HEAD)
if [ "${#changed[@]}" -eq 0 ]; then
  echo "asset-version-bump: no changed files."
  exit 0
fi

fail=0
for base in wp-content/plugins wp-content/themes; do
  # Unique top-level plugin/theme dirs that have any change in this diff.
  while read -r dir; do
    [ -z "$dir" ] && continue
    is_exempt "$dir" && continue

    # Did this dir change a front-end asset?
    assets_changed=0
    for f in "${changed[@]}"; do
      [[ "$f" == "$dir"/* ]] || continue
      case "$f" in
        *.js|*.css) assets_changed=1; break ;;
      esac
    done
    [ "$assets_changed" -eq 0 ] && continue

    # Require an added version line in the same dir: either the plugin/theme
    # header "Version:" or a *_VERSION constant assignment. (grep without -q so
    # it consumes all input — under `set -o pipefail`, an early-exiting `grep -q`
    # would SIGPIPE `git diff` and make a real match look like a failure.)
    version_bumped="$(git diff "$MB" HEAD -- "$dir" | grep -E '^\+.*([Vv]ersion:[[:space:]]|_VERSION[[:space:]]*=)' || true)"
    if [ -n "$version_bumped" ]; then
      continue
    fi

    echo "::error::$dir changed front-end assets (*.js/*.css) without bumping its version."
    echo "         Bump the plugin/theme version (header Version: and any *_VERSION enqueue constant)"
    echo "         so the asset cache-buster changes and browsers/Cloudflare refetch."
    fail=1
  done < <(printf '%s\n' "${changed[@]}" | awk -F/ -v b="$base" '$0 ~ "^"b"/" && NF>=3 {print $1"/"$2"/"$3}' | sort -u)
done

if [ "$fail" -eq 0 ]; then
  echo "asset-version-bump: OK (no asset change is missing a version bump)."
fi
exit "$fail"
