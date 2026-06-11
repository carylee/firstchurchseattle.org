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

# Multiplex every rsync over ONE SSH connection. This script opens ~11 separate
# connections (one rsync per path); HostGator rate-limits SSH and refuses the later
# ones, so the trailing rsyncs (the ~/bin cron script, bulletin/index.php) failed in
# CI with "connect ... Connection refused". It worked from a dev box only because our
# ~/.ssh/config already multiplexes — the CI runner's ssh does not. ControlMaster=auto
# opens the master on the first rsync; the rest reuse it (no new handshakes → no rate
# limit). ControlPersist keeps it briefly so every rsync in this run shares it.
MUX="-o ControlMaster=auto -o ControlPath=${TMPDIR:-/tmp}/fc-deploy-%r@%h:%p -o ControlPersist=120s"
RSH="ssh $MUX"; [ -f "$KEY" ] && RSH="ssh -o IdentitiesOnly=yes -i $KEY $MUX"
# Tear the shared master down on exit (ControlPersist would expire it anyway; tidy on CI).
trap 'ssh $MUX -O exit firstchurch 2>/dev/null || true' EXIT

REMOTE="firstchurch:public_html/wp-content"

# Guard: assets/tailwind.css is a BUILT artifact (gitignored, not committed). The
# CD workflow compiles it on the runner before invoking this script; a manual
# deploy must build it first (./build-css.sh, needs Node) or pull it (ddev
# pull-prod). Refuse rather than let the theme's --delete mirror (below) wipe
# prod's copy and leave the site unstyled.
TW="wp-content/themes/maranatha-child/assets/tailwind.css"
if [ -z "$DRY" ] && [ ! -f "$TW" ]; then
  echo "ERROR: $TW is missing — it's built, not committed." >&2
  echo "  Build it:  wp-content/themes/maranatha-child/build-css.sh   (needs Node)" >&2
  echo "  or pull it: ddev pull-prod --files-only" >&2
  exit 1
fi

# Theme + plugin are fully ours -> mirror with --delete. The child theme now
# carries a dev-only JS toolchain (Node modules, Vitest/Playwright tests, config)
# that must NOT ship to prod — prod runs no build step. Exclude those, the same
# way the TDD'd plugins exclude their Composer/PHPUnit artifacts. The built
# tailwind.css (verified present above) and the committed assets/js/ DO ship.
rsync -av $DRY --delete \
  --exclude='node_modules/' --exclude='tests/' --exclude='e2e/' \
  --exclude='playwright-report/' --exclude='test-results/' --exclude='coverage/' \
  --exclude='package.json' --exclude='package-lock.json' \
  --exclude='biome.json' --exclude='vitest.config.js' --exclude='playwright.config.js' \
  -e "$RSH" wp-content/themes/maranatha-child/ \
  "$REMOTE/themes/maranatha-child/"
# connection-card is fully ours, but (like breeze-forms below) its working tree
# carries dev-only artifacts (Composer deps, PHPUnit cache/config, tests) that
# must NOT ship to prod. Mirror with --delete but exclude those.
rsync -av $DRY --delete \
  --exclude='vendor/' --exclude='.phpunit.cache/' --exclude='tests/' \
  --exclude='composer.json' --exclude='composer.lock' --exclude='phpunit.xml.dist' \
  -e "$RSH" wp-content/plugins/firstchurch-connection-card/ \
  "$REMOTE/plugins/firstchurch-connection-card/"
# carousel is fully ours, but (like breeze-forms below) its working tree carries
# dev-only artifacts (Composer deps, PHPUnit cache/config, tests) that must NOT
# ship to prod. Mirror with --delete but exclude those.
rsync -av $DRY --delete \
  --exclude='vendor/' --exclude='.phpunit.cache/' --exclude='tests/' \
  --exclude='composer.json' --exclude='composer.lock' --exclude='phpunit.xml.dist' \
  -e "$RSH" wp-content/plugins/firstchurch-carousel/ \
  "$REMOTE/plugins/firstchurch-carousel/"
rsync -av $DRY --delete \
  --exclude='vendor/' --exclude='.phpunit.cache/' --exclude='tests/' \
  --exclude='composer.json' --exclude='composer.lock' --exclude='phpunit.xml.dist' \
  -e "$RSH" wp-content/plugins/firstchurch-stock-photos/ \
  "$REMOTE/plugins/firstchurch-stock-photos/"

# breeze-forms is fully ours too, but its working tree carries dev-only artifacts
# (Composer deps, PHPUnit cache/config, tests) that must NOT ship to prod.
rsync -av $DRY --delete \
  --exclude='vendor/' --exclude='.phpunit.cache/' --exclude='tests/' \
  --exclude='composer.json' --exclude='composer.lock' --exclude='phpunit.xml.dist' \
  -e "$RSH" wp-content/plugins/firstchurch-breeze-forms/ \
  "$REMOTE/plugins/firstchurch-breeze-forms/"

# events is fully ours. Unlike the others, rlanvin/php-rrule is a RUNTIME dep
# vendored under lib/, so lib/ SHIPS — only the dev artifacts (Composer/PHPUnit)
# are excluded. NOTE: after first deploy, activate it:
#   ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-events'
rsync -av $DRY --delete \
  --exclude='vendor/' --exclude='.phpunit.cache/' --exclude='tests/' \
  --exclude='composer.json' --exclude='composer.lock' --exclude='phpunit.xml.dist' \
  -e "$RSH" wp-content/plugins/firstchurch-events/ \
  "$REMOTE/plugins/firstchurch-events/"

# people is fully ours (the ctc_person replacement). No runtime deps — exclude the
# dev artifacts like the standard plugins. Its registration stays DORMANT while
# Church Theme Content still owns ctc_person; activating only turns on the live,
# additive pieces (MCP create/update-person). NOTE: after first deploy, activate it
# and flush rewrites for the /staff/ rule:
#   ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-people && wp rewrite flush'
rsync -av $DRY --delete \
  --exclude='vendor/' --exclude='.phpunit.cache/' --exclude='tests/' \
  --exclude='composer.json' --exclude='composer.lock' --exclude='phpunit.xml.dist' \
  -e "$RSH" wp-content/plugins/firstchurch-people/ \
  "$REMOTE/plugins/firstchurch-people/"

# happenings (the spine) is fully ours and TDD'd like breeze-forms — same dev-only
# artifacts to exclude. NOTE: firstchurch-carousel depends on this; after the first
# deploy run `ssh firstchurch 'wp plugin activate firstchurch-happenings'`.
rsync -av $DRY --delete \
  --exclude='vendor/' --exclude='.phpunit.cache/' --exclude='tests/' \
  --exclude='composer.json' --exclude='composer.lock' --exclude='phpunit.xml.dist' \
  -e "$RSH" wp-content/plugins/firstchurch-happenings/ \
  "$REMOTE/plugins/firstchurch-happenings/"

# e-news (the weekly newsletter as a spine surface — enews_issue CPT + email
# render). TDD'd like happenings, so the same dev-only artifacts are excluded.
# NOTE: after the first deploy, activate it and flush rewrites for the /enews/ slug:
#   ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-enews && wp rewrite flush'
rsync -av $DRY --delete \
  --exclude='vendor/' --exclude='.phpunit.cache/' --exclude='tests/' \
  --exclude='composer.json' --exclude='composer.lock' --exclude='phpunit.xml.dist' \
  -e "$RSH" wp-content/plugins/firstchurch-enews/ \
  "$REMOTE/plugins/firstchurch-enews/"

# mu-plugins/ ALSO holds host must-use plugins (endurance-page-cache) we do NOT track,
# so sync our files individually and NEVER --delete this directory.
rsync -av $DRY -e "$RSH" wp-content/mu-plugins/firstchurch-mcp-abilities.php          "$REMOTE/mu-plugins/"
rsync -av $DRY -e "$RSH" wp-content/mu-plugins/sso.php                                "$REMOTE/mu-plugins/"
rsync -av $DRY -e "$RSH" wp-content/mu-plugins/firstchurch-google-register-policy.php "$REMOTE/mu-plugins/"
rsync -av $DRY -e "$RSH" wp-content/mu-plugins/firstchurch-google-login-callback.php  "$REMOTE/mu-plugins/"
rsync -av $DRY -e "$RSH" wp-content/mu-plugins/firstchurch-security-headers.php       "$REMOTE/mu-plugins/"

# Uploads CORS: one .htaccess that lets the slides editor fetch upload images
# cross-origin to bake the announcement carousel GIF (Apache serves uploads
# statically, so this can't be a PHP/mu-plugin hook). Sync ONLY this file —
# uploads/ is the prod image library and must NEVER be --deleted.
rsync -av $DRY -e "$RSH" wp-content/uploads/.htaccess "$REMOTE/uploads/.htaccess"

# Server-side cron script, kept OUTSIDE the webroot (~/bin, not web-served).
rsync -av $DRY -e "$RSH" ops/bin/ firstchurch:bin/

# Custom webroot code (served from public_html root) — NEVER --delete the webroot.
rsync -av $DRY -e "$RSH" bulletin/index.php firstchurch:public_html/bulletin/

echo
echo "Deployed. Recommended post-deploy lint:"
echo "  ssh firstchurch 'php -l ~/public_html/wp-content/mu-plugins/firstchurch-mcp-abilities.php'"
