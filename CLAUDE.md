# First Church Seattle — website (`firstchurchseattle.org`)

**One repo, one place.** This directory is simultaneously:

1. **The source of truth for our custom code** (child theme, connection-card plugin, MCP
   mu-plugin, deploy/ops tooling) — git-tracked, deployed to production from here.
2. **A full local mirror of the live site** running under **DDEV** — WordPress core, uploads,
   third-party plugins, and the database, refreshed from prod on demand.

`.gitignore` keeps these straight: `git status` only ever shows *our* code, even though the
working tree is ~6 GB. The detailed boundary is **[`ops/sync/ownership.md`](./ops/sync/ownership.md)**.

> Replaced (2026-06-03): the old split between `../web/` (code repo) and
> `../website/firstchurchseattle.org/` (mirror). Both collapsed here. Pre-WordPress archival
> junk moved to `../archive/legacy-site/`.

---

## Quickstart

```bash
# Prereqs (declared in home-manager): OrbStack (Docker provider) + ddev + mkcert.
ddev start                 # → https://firstchurchseattle.ddev.site
ddev launch                # open in browser
```

## The two sync directions

```
        ops/deploy.sh  (push: our code ──▶ prod)
  this repo  ⇅  firstchurchseattle.org (HostGator)
        ddev pull-prod (pull: prod ──▶ core/uploads/db)
```

**Pull — refresh the mirror from production:**
```bash
ddev pull-prod             # files (core/uploads/3rd-party) + database
ddev pull-prod -n          # dry run
ddev pull-prod --db-only   # or --files-only
```

**Push — deploy our code to production:**
```bash
ops/deploy.sh -n           # dry run
ops/deploy.sh              # deploy
```

Both honor one ownership model so they can't conflict — see `ops/sync/ownership.md`.

## How to work on the site

| You want to… | Do this |
|---|---|
| Change the **theme / a custom plugin / the MCP mu-plugin** | Edit it in place under `wp-content/…` → preview live at `*.ddev.site` → commit → `ops/deploy.sh` |
| Edit **content** (events, posts, sermons, announcements) | That's data, edited on prod via the MCP server; `ddev pull-prod --db-only` to pull it down |
| **Refresh** your local copy of prod | `ddev pull-prod` |
| Run **wp-cli** locally | `ddev wp <args>` |
| Open a **DB shell** | `ddev mysql` |

**Discipline:** edit Class-A code *here and deploy it* — don't edit the theme/plugins
directly on prod. The pull excludes tracked code, so a direct-prod edit won't come back and
the next deploy will overwrite it. (This is the drift the old two-repo setup suffered.)

## What's tracked vs. mirrored

```
firstchurchseattle.org/                 ← git repo + DDEV project
├── CLAUDE.md, README.md, .gitignore    ← tracked
├── .ddev/                              ← tracked (config + the pull-prod command)
├── ops/                               ← tracked — see below
├── wp-content/
│   ├── themes/maranatha-child/         ← TRACKED  (our theme)
│   ├── plugins/firstchurch-connection-card/  ← TRACKED
│   ├── mu-plugins/firstchurch-mcp-abilities.php, sso.php  ← TRACKED
│   └── …core/uploads/third-party…      ← mirrored from prod, gitignored
├── bulletin/index.php                  ← TRACKED (web bulletin server); *.pdf/*.html mirrored
├── wp-admin/, wp-includes/, prod subdirs  ← mirrored, gitignored
└── db/                                ← local DB dumps (gitignored)
```

### `ops/` (tracked tooling)
| Path | What |
|---|---|
| `ops/deploy.sh` | **Push** to prod |
| `ops/sync/ownership.md` | The push/pull ownership model (start here) |
| `ops/sync/pull-exclude.txt` | What the pull never overwrites |
| `ops/scripts/setup-roles.sh` | Recreate the `mcp_editor` role + MCP users |
| `ops/scripts/install-mcp-adapter.sh` | Reinstall the pinned MCP Adapter |
| `ops/manifests/` | Snapshots of prod plugins/themes/crontab |
| `ops/docs/databases.md` | MySQL DB reference / cleanup |
| `ops/bin/update-enews-redirect.php` | Cron: repoint `/enews/latest` at the newest e-news |

The `ddev pull-prod` command itself lives at `.ddev/commands/host/pull-prod`.

---

## Server / production facts

- **SSH:** `ssh firstchurch` (HostGator). WP root: `~/public_html`. Auth via
  `~/src/church/church.pem` (pinned in the `firstchurch` block of `~/.ssh/config`).
- **Versions:** WordPress **7.0**, PHP 8.2 (web) / 8.3 (CLI), WP-CLI 2.9.
- **WP-Cron** runs from a **real crontab every 15 min** (Cloudflare blocks the WP loopback;
  `DISABLE_WP_CRON` is set). Don't "fix" cron by re-enabling the loopback.
- **MCP server:** `wp-json/mcp/mcp-adapter-default-server` — content CRUD for AI agents
  (events/sermons/announcements/posts/pages/media/redirects). Two identities: `mcp-client`
  (read) and `mcp-editor` (scoped writes). Details in `wp-content/mu-plugins/firstchurch-mcp-abilities.php`.
- **Backups:** UpdraftPlus, monthly, to Google Drive (DB/content not in git).
- **Known prod note:** the live child theme had been edited directly and was ahead of the old
  `web/` repo; this repo's initial state captures prod. The site also still serves
  `paxchristiyoga.org/` (a separate site) under `public_html` — mirrored but not ours.

## Local dev wiring (DDEV)

- Provider **OrbStack**; `php=8.3`, `webserver=apache-fpm` (HostGator parity),
  `database=mariadb:10.11`, `performance_mode=none` (direct mount).
- DDEV manages `wp-config.php`; the `wpqg_` table prefix comes from `web_environment:
  DB_PREFIX=wpqg_`; URLs rewrite to `*.ddev.site` at runtime (no DB search-replace).
- For trusted local HTTPS: `mkcert -install` once.
