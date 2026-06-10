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
  "wp-content/themes/maranatha"          # vendored parent theme: pinned for drift detection, not ours to push
  "wp-content/mu-plugins/firstchurch-mcp" # dev-only test harness; WP never loads mu-plugin subdirs
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

# mu-plugins are synced as INDIVIDUAL files in deploy.sh (the dir also holds
# host must-use plugins we don't own, so it's never mirrored). A new tracked
# top-level mu-plugin .php therefore needs its own rsync line — without one it
# merges green and silently never ships, same trap as an unwired plugin dir.
while read -r f; do
  [ -z "$f" ] && continue
  if ! grep -qF "$f" "$DEPLOY"; then
    echo "::error::$f is tracked but not referenced in $DEPLOY."
    echo "  → Add an rsync line for it in $DEPLOY (sync the single file; NEVER --delete mu-plugins/)."
    fail=1
  fi
done < <(git ls-files wp-content/mu-plugins | awk -F/ 'NF==3' | sort -u)

# Tracked SUBDIRS of mu-plugins (WordPress doesn't auto-load these) must be
# deployed explicitly or listed in NOT_DEPLOYED above.
while read -r dir; do
  [ -z "$dir" ] && continue
  is_exempt "$dir" && continue
  if ! grep -qF "$dir/" "$DEPLOY"; then
    echo "::error::$dir is tracked but not referenced in $DEPLOY."
    echo "  → Add an rsync line for it in $DEPLOY, or add it to NOT_DEPLOYED in"
    echo "    ops/scripts/check-deploy-coverage.sh if it is intentionally not deployed."
    fail=1
  fi
done < <(git ls-files wp-content/mu-plugins | awk -F/ 'NF>=4 {print $1"/"$2"/"$3}' | sort -u)

if [ "$fail" -eq 0 ]; then
  echo "Deploy coverage OK: every tracked plugin/theme/mu-plugin is wired into $DEPLOY (or explicitly exempt)."
fi
exit $fail
