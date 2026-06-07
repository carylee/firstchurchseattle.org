#!/usr/bin/env bash
#
# GUARDRAIL: every tracked custom plugin/theme directory must be wired into
# ops/deploy.sh — or explicitly listed below as intentionally-not-deployed.
#
# Why this exists: ops/deploy.sh is an explicit allowlist of rsync paths, NOT a
# wildcard over wp-content/. A brand-new plugin gets tracked and merged but ships
# NOTHING to prod until someone adds a matching rsync line — and because the CD
# pipeline just runs deploy.sh, it goes green while silently skipping the new
# code. (That's exactly how firstchurch-carousel missed its first deploy.) This
# check fails CI the moment a tracked plugin/theme has no deploy coverage, so the
# omission can't reach a "successful" deploy.
#
# Run locally:  ops/scripts/check-deploy-coverage.sh
set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$REPO"

DEPLOY="ops/deploy.sh"

# Tracked directories that are intentionally NOT deployed. Keep short + justified.
NOT_DEPLOYED=(
  "wp-content/themes/maranatha"   # vendored parent theme: pinned for drift detection, not ours to push
)

is_exempt() {
  local dir="$1" e
  for e in "${NOT_DEPLOYED[@]}"; do
    [ "$dir" = "$e" ] && return 0
  done
  return 1
}

fail=0
# Every top-level tracked directory under plugins/ and themes/.
for base in wp-content/plugins wp-content/themes; do
  while read -r dir; do
    [ -z "$dir" ] && continue
    is_exempt "$dir" && continue
    # deploy.sh references each deployed dir with a trailing slash, e.g.
    #   rsync ... wp-content/plugins/firstchurch-carousel/ ...
    if ! grep -qF "$dir/" "$DEPLOY"; then
      echo "::error::$dir is tracked but not referenced in $DEPLOY."
      echo "  → Add an rsync line for it in $DEPLOY (mirror with --delete if it is fully ours),"
      echo "    or add it to NOT_DEPLOYED in ops/scripts/check-deploy-coverage.sh if it is intentionally not deployed."
      fail=1
    fi
  done < <(git ls-files "$base" | awk -F/ 'NF>=3 {print $1"/"$2"/"$3}' | sort -u)
done

if [ "$fail" -eq 0 ]; then
  echo "Deploy coverage OK: every tracked plugin/theme is wired into $DEPLOY (or explicitly exempt)."
fi
exit $fail
