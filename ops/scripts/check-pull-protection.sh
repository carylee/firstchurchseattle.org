#!/usr/bin/env bash
#
# GUARDRAIL: every tracked custom plugin/theme/mu-plugin must be protected from
# `ddev pull-prod` via ops/sync/pull-exclude.txt — or explicitly listed below as
# intentionally pull-overwritten.
#
# Why this exists: the pull is ONE rsync of the whole WP root with --delete,
# guarded only by the exclude list. A tracked plugin missing from the list gets
# overwritten by prod's copy — and because deploys exclude tests/, composer.json
# and vendor/, prod's copy LACKS them, so the pull's --delete silently wipes the
# local test suite. A tracked dir that never ships to prod at all (a dev-only
# harness) gets deleted outright. This is the pull-side twin of
# check-deploy-coverage.sh.
#
# Run locally:  ops/scripts/check-pull-protection.sh
set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$REPO"

EXCLUDE="ops/sync/pull-exclude.txt"

# Tracked paths that are intentionally NOT protected — the pull overwrites them
# on purpose. Keep short + justified.
PULLED_ON_PURPOSE=(
  "wp-content/themes/maranatha"   # vendored parent theme: pulled so upstream drift shows in git status
)

is_exempt() {
  local path="$1" e
  for e in "${PULLED_ON_PURPOSE[@]}"; do
    [ "$path" = "$e" ] && return 0
  done
  return 1
}

# A path is protected if the exclude list carries it verbatim (anchored, with or
# without the trailing slash rsync also accepts).
is_protected() {
  grep -qxF "/$1" "$EXCLUDE" || grep -qxF "/${1%/}" "$EXCLUDE"
}

fail=0
flag() {
  echo "::error::$1 is tracked but not protected in $EXCLUDE."
  echo "  → Add '/$1' to $EXCLUDE (the pull's rsync --delete will otherwise clobber it),"
  echo "    or add it to PULLED_ON_PURPOSE in ops/scripts/check-pull-protection.sh if the"
  echo "    pull is meant to overwrite it."
  fail=1
}

# Top-level tracked dirs under plugins/ and themes/ (same discovery as the
# deploy-coverage check) and under mu-plugins/.
for base in wp-content/plugins wp-content/themes wp-content/mu-plugins; do
  while read -r dir; do
    [ -z "$dir" ] && continue
    is_exempt "$dir" && continue
    is_protected "$dir/" || flag "$dir/"
  done < <(git ls-files "$base" | awk -F/ 'NF>=4 {print $1"/"$2"/"$3}' | sort -u)
done

# Tracked single files directly under mu-plugins/ (WordPress auto-loads these).
while read -r f; do
  [ -z "$f" ] && continue
  is_exempt "$f" && continue
  is_protected "$f" || flag "$f"
done < <(git ls-files wp-content/mu-plugins | awk -F/ 'NF==3' | sort -u)

if [ "$fail" -eq 0 ]; then
  echo "Pull protection OK: every tracked plugin/theme/mu-plugin is excluded from the pull (or exempt on purpose)."
fi
exit $fail
