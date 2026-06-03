#!/usr/bin/env bash
#
# Ensure the MCP role + users on the server (idempotent — safe to re-run).
# Run from the repo root:  ./scripts/setup-roles.sh
#
# This recreates the *capability* design only. Application passwords are secrets
# and are NOT managed here — mint them separately and store in a password manager.
set -euo pipefail

# Exact capability set for the writer role.
# publish_posts / publish_pages ARE granted: the role may publish (and schedule/
# backdate) the content types it manages, via the abilities OR core REST. Scope is
# still constrained in code by fcmcp_is_managed_post + a map_meta_cap filter, which
# limits edit/delete/publish to events, sermons, posts, and pages — attachments,
# users, settings, and other CPTs remain out of reach for the app-password credential.
#
# fcmcp_manage_redirects is a custom, narrow cap that gates ONLY the redirect
# abilities (Redirection plugin) — it is NOT manage_options, so it does not open
# the full Redirection admin or core REST. Redirect writes go through the
# controlled abilities, which call the Red_Item model directly.
EDITOR_CAPS="read \
edit_posts edit_others_posts edit_published_posts publish_posts \
delete_posts delete_others_posts delete_published_posts \
edit_pages edit_others_pages edit_published_pages publish_pages \
delete_pages delete_others_pages delete_published_pages \
upload_files \
fcmcp_manage_redirects"

ssh firstchurch 'bash -l -s' <<REMOTE
set -e
cd ~/public_html

# --- writer role: mcp_editor ---
if ! wp role exists mcp_editor >/dev/null 2>&1; then
  wp role create mcp_editor "MCP Editor"
fi
wp cap add mcp_editor ${EDITOR_CAPS}

# --- users (role assignment only; app passwords minted separately) ---
if wp user get mcp-client >/dev/null 2>&1; then
  wp user set-role mcp-client subscriber
else
  wp user create mcp-client mcp-client@firstchurchseattle.org --role=subscriber --display_name="MCP Client" --porcelain
fi
if wp user get mcp-editor >/dev/null 2>&1; then
  wp user set-role mcp-editor mcp_editor
else
  wp user create mcp-editor mcp-editor@firstchurchseattle.org --role=mcp_editor --display_name="MCP Editor" --porcelain
fi

echo
echo "mcp_editor capabilities now:"
wp cap list mcp_editor | sort | tr '\n' ' '
echo
REMOTE

echo
echo "Done. Application passwords are NOT managed by this script (they are secrets)."
echo "If a user has none yet, mint one (shown ONCE) and store it in your password manager:"
echo "  ssh firstchurch \"wp --path=~/public_html user application-password create mcp-editor 'MCP Editor' --porcelain\""
echo "  ssh firstchurch \"wp --path=~/public_html user application-password create mcp-client 'MCP Client' --porcelain\""
