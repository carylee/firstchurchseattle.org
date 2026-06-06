# Sync ownership boundary (push ⇄ pull)

This repo is **both** the source of truth for our custom code **and** a full local mirror of
production, run under DDEV. One boundary governs both sync directions so they can't drift:

- **Push** (local → prod): `ops/deploy.sh` — ships the code we author.
- **Pull** (prod → local): `ddev pull-prod` — refreshes core/uploads/third-party + the DB.

Every path under the repo (= the WordPress root) is exactly one of three classes:

| Class | What | Examples | Git? | Push? | Pull? |
|------|------|----------|:----:|:----:|:----:|
| **A — Owned code** | We author it; git is the source of truth | `wp-content/themes/maranatha-child/`, `wp-content/plugins/firstchurch-connection-card/`, `wp-content/plugins/firstchurch-breeze-forms/`, `wp-content/mu-plugins/firstchurch-*.php`, `bulletin/index.php`, `ops/` | **tracked** | ✅ deploy | ❌ excluded (git leads) |
| **B — Prod-authoritative** | WordPress core, third-party, content | WP core, `wp-content/uploads/`, 3rd-party plugins/themes, the database, `paxchristiyoga.org/` & other prod subdirs | gitignored | ❌ never | ✅ mirrored |
| **C — Local-only archives** | Pre-WP geology, irreplaceable | *(removed from the tree)* — now in `../archive/legacy-site/` | — | — | — |

The litmus test: **A** = "we wrote it" → tracked + deployed. **B** = "prod owns it" → gitignored + pulled. **C** is gone — the Joomla geology (incl. the only-copy 2009–2011 sermon mp3s) was moved to `../archive/legacy-site/` so it no longer clutters the working tree.

## How the two directions stay coherent

- **Class A is excluded from the pull** ([`pull-exclude.txt`](./pull-exclude.txt)): git is
  authoritative for our code, so a pull never clobbers in-progress edits. You **edit Class A
  in this repo, preview at `*.ddev.site`, then `ops/deploy.sh`**. Don't edit it directly on
  prod — a pull won't capture that, and the next deploy overwrites it.
- **Class B is pulled** and **gitignored** (the `.gitignore` is the inverse of this table):
  `git status` only ever shows Class A even though the tree is ~6 GB.
- **The database** is always pulled (prod content lives there; it's edited on prod via the
  MCP server, not in files). The pulled copy is then **sanitized locally** (`ddev sanitize-db`,
  run automatically by `pull-prod` unless `--no-sanitize`): visitor IPs, the maintenance-mode
  subscriber list, and staff emails are scrubbed so dev machines don't carry production PII.
  This is local-only and never touches prod. Production *backups*, by contrast, are full and
  unsanitized — see [`../docs/backups.md`](../docs/backups.md).

## Push nuances (in `ops/deploy.sh`)

- Theme + connection-card + breeze-forms: fully ours → `rsync --delete` (breeze-forms
  excludes dev-only `vendor/`, `tests/`, Composer/PHPUnit config).
- `mu-plugins/`: synced **file-by-file, never `--delete`** (the dir also holds the host
  must-use `endurance-page-cache` we don't track).
- `ops/bin/` → `~/bin` (cron scripts, outside the webroot); `bulletin/index.php` → webroot.

> **Drift note (2026-06-03):** at consolidation, prod's `maranatha-child` had been edited
> directly and was *ahead* of the old `web/` repo (an `inc/` dir, `content-footer-short.php`,
> tweaked `functions.php`/`mobile.css`). The initial commit here captures prod's live state;
> the single-repo + "deploy from git" discipline is what prevents this recurring.
