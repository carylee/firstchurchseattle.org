#!/usr/bin/env bash
#
# PUSH: deploy this repo's tracked custom code to firstchurchseattle.org (HostGator).
# Run from anywhere:  ops/deploy.sh        (add -n for a dry run)
#
# This is the push half of the sync boundary (local -> prod); the pull half is
# `ddev pull-prod`. Both honor one ownership model — see ops/sync/ownership.md.
# The deploy source is the in-place tracked code in THIS repo (the single source of
# truth); the per-path --delete nuances are documented inline below.
set -euo pipefail

DRY=""
[ "${1:-}" = "-n" ] && DRY="--dry-run" && echo "(dry run)"

REPO="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO"

# Pin the church.pem key explicitly (robust regardless of ~/.ssh/config / agent state).
KEY="${FIRSTCHURCH_KEY:-$(dirname "$REPO")/church.pem}"
RSH="ssh"; [ -f "$KEY" ] && RSH="ssh -o IdentitiesOnly=yes -i $KEY"

REMOTE="firstchurch:public_html/wp-content"

# Theme + plugin are fully ours -> mirror with --delete.
rsync -av $DRY --delete -e "$RSH" wp-content/themes/maranatha-child/ \
  "$REMOTE/themes/maranatha-child/"
rsync -av $DRY --delete -e "$RSH" wp-content/plugins/firstchurch-connection-card/ \
  "$REMOTE/plugins/firstchurch-connection-card/"

# breeze-forms is fully ours too, but its working tree carries dev-only artifacts
# (Composer deps, PHPUnit cache/config, tests) that must NOT ship to prod.
rsync -av $DRY --delete \
  --exclude='vendor/' --exclude='.phpunit.cache/' --exclude='tests/' \
  --exclude='composer.json' --exclude='composer.lock' --exclude='phpunit.xml.dist' \
  -e "$RSH" wp-content/plugins/firstchurch-breeze-forms/ \
  "$REMOTE/plugins/firstchurch-breeze-forms/"

# mu-plugins/ ALSO holds host must-use plugins (endurance-page-cache) we do NOT track,
# so sync our files individually and NEVER --delete this directory.
rsync -av $DRY -e "$RSH" wp-content/mu-plugins/firstchurch-mcp-abilities.php "$REMOTE/mu-plugins/"
rsync -av $DRY -e "$RSH" wp-content/mu-plugins/sso.php                       "$REMOTE/mu-plugins/"

# Server-side cron script, kept OUTSIDE the webroot (~/bin, not web-served).
rsync -av $DRY -e "$RSH" ops/bin/ firstchurch:bin/

# Custom webroot code (served from public_html root) — NEVER --delete the webroot.
rsync -av $DRY -e "$RSH" bulletin/index.php firstchurch:public_html/bulletin/

echo
echo "Deployed. Recommended post-deploy lint:"
echo "  ssh firstchurch 'php -l ~/public_html/wp-content/mu-plugins/firstchurch-mcp-abilities.php'"
