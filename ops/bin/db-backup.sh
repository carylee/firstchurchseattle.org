#!/usr/bin/env bash
#
# Daily production DB backup. Deployed to ~/bin by ops/deploy.sh and run from the
# HostGator crontab (see ops/manifests/crontab.txt). Dumps the WordPress database,
# gzips it OUTSIDE the webroot, rotates local copies, and (if an rclone remote is
# configured) ships a copy off-site.
#
# This is a FULL dump — a true restore source. (The local dev pull, by contrast,
# is sanitized/trimmed; see .ddev/commands/web/sanitize-db.) Restorability is
# checked separately by `ddev restore-test`.
#
# All paths/tunables are overridable via env so the script can be smoke-tested
# locally, e.g.:
#   WP="ddev wp" BACKUP_DIR=/tmp/bk RCLONE_REMOTE= ops/bin/db-backup.sh
set -euo pipefail

WP="${WP:-wp --path=$HOME/public_html}"   # how to invoke wp-cli (prod default)
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/db}"
KEEP_DAYS="${KEEP_DAYS:-14}"
RCLONE_BIN="${RCLONE_BIN:-$HOME/bin/rclone}"
RCLONE_REMOTE="${RCLONE_REMOTE-firstchurch-backup:db}"  # set to empty string to skip off-site

mkdir -p "$BACKUP_DIR"
ts="$(date +%F)"
dump="$BACKUP_DIR/firstchurch-${ts}.sql.gz"

echo "==> Dumping DB -> $dump"
# shellcheck disable=SC2086  # $WP is an intentional command + args
$WP db export - --single-transaction --quick --default-character-set=utf8mb4 | gzip > "$dump"
ln -sf "$(basename "$dump")" "$BACKUP_DIR/latest.sql.gz"
echo "    $(du -h "$dump" | cut -f1)"

echo "==> Rotating local copies older than ${KEEP_DAYS}d"
find "$BACKUP_DIR" -name 'firstchurch-*.sql.gz' -type f -mtime +"$KEEP_DAYS" -print -delete

if [ -n "$RCLONE_REMOTE" ] && [ -x "$RCLONE_BIN" ]; then
  echo "==> Off-site: rclone copy -> $RCLONE_REMOTE"
  "$RCLONE_BIN" copy "$dump" "$RCLONE_REMOTE"
  # Mirror the local retention window on the remote.
  "$RCLONE_BIN" delete --min-age "${KEEP_DAYS}d" "$RCLONE_REMOTE" || true
else
  echo "==> Off-site: skipped (rclone not configured — RCLONE_REMOTE='$RCLONE_REMOTE', bin='$RCLONE_BIN')"
fi

echo "==> Done: $ts"
