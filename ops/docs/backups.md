# Backups & restore

Layered, so a single failure mode never loses much:

| Layer | What | Cadence | Where | Retention |
|---|---|---|---|---|
| **UpdraftPlus** | full site (files + DB) | monthly | Google Drive | per UpdraftPlus |
| **`db-backup.sh`** | DB only, full dump | **daily** (cron) | `~/backups/db/` on the server **+** off-site via rclone | 14 days |
| **`ddev restore-test`** | proves a dump restores | on demand / weekly | local throwaway DB | — |

The daily DB dump closes the gap between monthly UpdraftPlus snapshots — the
database (events, sermons, announcements edited via MCP) changes far more often
than monthly. It is a **full** dump (a true restore source); the *local dev*
copy is separately trimmed/sanitized (see [pull-prod](#relationship-to-pull-prod)).

## `ops/bin/db-backup.sh`

Deployed to `~/bin/` by `ops/deploy.sh`. Dumps the DB with `wp db export`, gzips
it **outside the webroot** (never web-served), symlinks `latest.sql.gz`, rotates
local copies older than `KEEP_DAYS` (14), and — if rclone is configured — copies
off-site and mirrors the same retention there.

Tunables (env overrides, prod defaults shown):
```
WP="wp --path=$HOME/public_html"   BACKUP_DIR="$HOME/backups/db"
KEEP_DAYS=14                       RCLONE_BIN="$HOME/bin/rclone"
RCLONE_REMOTE="firstchurch-backup:db"   # set to "" to skip off-site
```

## One-time prod setup (to activate)

1. **Install rclone** (no sudo needed — user-space binary):
   ```bash
   ssh firstchurch
   mkdir -p ~/bin && cd /tmp
   curl -fsSL https://downloads.rclone.org/rclone-current-linux-amd64.zip -o rclone.zip
   unzip -j rclone.zip '*/rclone' -d ~/bin && chmod +x ~/bin/rclone
   ```
2. **Configure a remote** named `firstchurch-backup`. A **key-based** remote
   (Backblaze B2 or S3) is easiest in a headless shell — no browser OAuth:
   ```bash
   ~/bin/rclone config    # n) new → name: firstchurch-backup → b2/s3 → paste keys
   ```
   (Or reuse the Google Drive that UpdraftPlus uses; Drive needs the OAuth dance.)
   Target a `db` path/bucket so the script's `firstchurch-backup:db` resolves.
3. **Add the cron line** (matches `ops/manifests/crontab.txt`):
   ```
   30 3 * * * /home3/seattle1/bin/db-backup.sh >> /home3/seattle1/logs/db-backup.log 2>&1
   ```
   `crontab -e`, paste, save. (`~/logs/` already exists for the e-news cron.)
4. **Deploy** so the script lands in `~/bin/`: `ops/deploy.sh`.

Until rclone is configured the script still runs and keeps **local** daily dumps
(it just logs "off-site: skipped").

## `ddev restore-test`

An untested backup is Schrödinger's backup. This pulls the latest prod dump,
imports it into a **throwaway** database in the DDEV db container (never your
working DB), asserts it carries real content (a `home` option, published events
and posts), then drops it.

```bash
ddev restore-test                       # pulls firstchurch:~/backups/db/latest.sql.gz
ddev restore-test --file=db/latest.sql.gz   # test a specific local dump
```
Run it after activating the cron (once real backups exist), then periodically.

## Relationship to pull-prod

`ddev pull-prod` pulls prod content down for local dev and **sanitizes** the
result (strips visitor IPs, the subscriber list, staff emails) via
`ddev sanitize-db`. That keeps PII off dev machines — see
[`ops/sync/ownership.md`](../sync/ownership.md). The *backups* here are the
opposite: deliberately **full and unsanitized**, because their job is to restore
production faithfully.
