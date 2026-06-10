#!/usr/bin/env bash
#
# Build the shippable, dependency-scoped plugin into ./dist/.
#
# WHY: prod runs no Composer (CLAUDE.md), and two plugins each carrying their own
# Guzzle would collide on class names (the bug firstchurch-events hit with
# php-rrule). So we resolve the prod deps, move them into a PRIVATE namespace with
# PHP-Scoper (FirstChurch\ENews\Vendor\…), and ship the result. Mirrors how the
# child theme compiles tailwind.css in CD — deploy.sh stays build-free and just
# rsyncs the artifact. See ops/docs/composer-on-prod.md.
#
# Usage (CD or a manual deploy):
#   PHP_SCOPER=/path/to/php-scoper.phar ops_or_local… ./build.sh
# php-scoper is a phar (NOT a composer dep — it would fight `composer install
# --no-dev`); CD downloads it, locally pass PHP_SCOPER=… or have `php-scoper` on PATH.
set -euo pipefail
cd "$(dirname "$0")"

SCOPER="${PHP_SCOPER:-php-scoper}"
export COMPOSER_ALLOW_SUPERUSER="${COMPOSER_ALLOW_SUPERUSER:-1}"

rm -rf dist

# 1. Production deps only, with a full optimized classmap (php-scoper prefixes it).
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction

# 2. Move deps (and rewrite our `use Vendor\…` references) into the private prefix.
php "$SCOPER" add-prefix --output-dir=dist --force --no-interaction

# 3. Regenerate the scoped autoloader (installed.json was copied in step 2).
composer dump-autoload --working-dir=dist --classmap-authoritative --no-dev --no-interaction

echo "Built dist/ — $(find dist -name '*.php' | wc -l | tr -d ' ') PHP files, $(du -sh dist | cut -f1) total."
