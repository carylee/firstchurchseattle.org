#!/usr/bin/env bash
#
# Reproduce the third-party WordPress MCP Adapter plugin install on the server.
# It is NOT vendored in this repo (it has its own upstream repo + composer deps);
# this script pins the exact commit we deployed and re-applies the install steps.
#
# Composer on the server lives at /opt/cpanel/composer/bin/composer and is only on
# the LOGIN-shell PATH, so we run inside `bash -lc`.
set -euo pipefail

PIN=530a541318c13d9039cb15cbd3d77507643218ab   # WordPress/mcp-adapter @ deployed commit

ssh firstchurch "bash -lc '
  set -e
  cd ~/public_html/wp-content/plugins
  [ -d mcp-adapter ] || git clone https://github.com/WordPress/mcp-adapter.git mcp-adapter
  cd mcp-adapter
  git fetch origin
  git checkout ${PIN}
  composer config allow-plugins.automattic/jetpack-autoloader true
  composer require automattic/jetpack-autoloader --update-no-dev --optimize-autoloader --no-interaction
  composer install --no-dev --optimize-autoloader --no-interaction
'"

echo
echo \"mcp-adapter installed @ ${PIN}.\"
echo \"Activate with: ssh firstchurch 'wp --path=~/public_html plugin activate mcp-adapter'\"
