# firstchurchseattle.org

The website for **First Church Seattle** — a WordPress site at
[firstchurchseattle.org](https://firstchurchseattle.org), hosted on HostGator.

This one repo is two things at once:

1. **The source of truth for our custom code** — the child theme, the connection-card
   plugin, the MCP mu-plugin, and the deploy/ops tooling. Git-tracked here, deployed to
   production from here.
2. **A full local mirror of the live site** running under **[DDEV](https://ddev.com)** —
   WordPress core, uploads, third-party plugins, and the database, refreshed from prod on
   demand.

`.gitignore` keeps the two straight: `git status` only ever shows *our* code (~30 files),
even though the working tree is ~6 GB. The detailed boundary is in
[`ops/sync/ownership.md`](./ops/sync/ownership.md).

> This replaced (2026-06-03) an older split between a `web/` code repo and a separate
> `website/` local mirror, which had drifted apart. Collapsing them into one repo with a
> single "deploy from git" discipline is what keeps prod and local from diverging again.

---

## Quickstart

**Prereqs:** OrbStack (Docker provider) + `ddev` + `mkcert` (declared in home-manager).

```bash
mkcert -install            # one-time, for trusted local HTTPS
ddev start                 # → https://firstchurchseattle.ddev.site
ddev launch                # open it in your browser
```

That boots a local copy of the whole site. To populate it with production content, pull
(see below).

---

## The two sync directions

```
        ops/deploy.sh   (push: our code ──▶ prod)
  this repo  ⇅  firstchurchseattle.org (HostGator)
        ddev pull-prod  (pull: prod ──▶ core / uploads / db)
```

Both honor one ownership model so they can't conflict — see
[`ops/sync/ownership.md`](./ops/sync/ownership.md).

**Push — deploy our code to production:**

```bash
ops/deploy.sh -n           # dry run
ops/deploy.sh              # deploy
```

**Pull — refresh the local mirror from production:**

```bash
ddev pull-prod             # files (core / uploads / 3rd-party) + database
ddev pull-prod -n          # dry run
ddev pull-prod --db-only   # or --files-only
```

---

## How to work on the site

| You want to… | Do this |
|---|---|
| Change the **theme / a custom plugin / the MCP mu-plugin** | Edit it in place under `wp-content/…` → preview at `*.ddev.site` → commit → `ops/deploy.sh` |
| Edit **content** (events, posts, sermons, announcements) | That's data — edited on prod via the MCP server; `ddev pull-prod --db-only` to pull it down |
| **Refresh** your local copy of prod | `ddev pull-prod` |
| Run **wp-cli** locally | `ddev wp <args>` |
| Open a **DB shell** | `ddev mysql` |

**Discipline:** edit our code *here and deploy it* — don't edit the theme/plugins directly
on prod. The pull excludes tracked code, so a direct-prod edit won't come back and the next
deploy will overwrite it.

---

## What's tracked vs. mirrored

Every path is one of two classes (full table in
[`ops/sync/ownership.md`](./ops/sync/ownership.md)):

- **Owned code** — we author it; git leads; deployed via `ops/deploy.sh`; *excluded* from the
  pull.
- **Prod-authoritative** — WP core, `wp-content/uploads/`, third-party plugins/themes, and
  the database; gitignored; refreshed via `ddev pull-prod`.

```
firstchurchseattle.org/                 ← git repo + DDEV project
├── README.md, CLAUDE.md, .gitignore    ← tracked
├── .ddev/                              ← tracked (config + the pull-prod command)
├── ops/                               ← tracked tooling (see below)
├── wp-content/
│   ├── themes/maranatha-child/         ← TRACKED  — our child theme
│   ├── plugins/firstchurch-connection-card/  ← TRACKED — website → Breeze form bridge
│   ├── mu-plugins/firstchurch-mcp-abilities.php, sso.php  ← TRACKED
│   └── …core / uploads / third-party…  ← mirrored from prod, gitignored
├── bulletin/index.php                  ← TRACKED (web bulletin server); *.pdf / *.html mirrored
├── wp-admin/, wp-includes/, prod subdirs  ← mirrored, gitignored
└── db/                                ← local DB dumps (gitignored)
```

### Our custom code

| Path | What it is |
|---|---|
| `wp-content/themes/maranatha-child/` | Child theme of [Maranatha](https://churchthemes.com/themes/maranatha) — mobile UX, layout polish, the `/worship/live/` template, announcements CTA. See its [README](./wp-content/themes/maranatha-child/README.md). |
| `wp-content/plugins/firstchurch-connection-card/` | Connection-card form on the live site, bridged to the church's Breeze ChMS (form 320238). |
| `wp-content/mu-plugins/firstchurch-mcp-abilities.php` | The site's MCP server — content CRUD (events, sermons, announcements, posts, pages, media, redirects) for AI agents. |
| `wp-content/mu-plugins/sso.php` | Single sign-on glue. |
| `bulletin/index.php` | The web-bulletin server at `/bulletin`. |

### `ops/` — tracked tooling

| Path | What |
|---|---|
| [`ops/deploy.sh`](./ops/deploy.sh) | **Push** to prod |
| [`ops/sync/ownership.md`](./ops/sync/ownership.md) | The push/pull ownership model (start here) |
| `ops/sync/pull-exclude.txt` | What the pull never overwrites |
| `ops/scripts/setup-roles.sh` | Recreate the `mcp_editor` role + MCP users |
| `ops/scripts/install-mcp-adapter.sh` | Reinstall the pinned MCP Adapter |
| `ops/manifests/` | Snapshots of prod plugins / themes / crontab |
| `ops/docs/databases.md` | MySQL DB reference / cleanup |
| `ops/docs/MAINTENANCE.md` | Prod maintenance punch-list |
| `ops/bin/update-enews-redirect.php` | Cron: repoint `/enews/latest` at the newest e-news |

The `ddev pull-prod` command itself lives at `.ddev/commands/host/pull-prod`.

---

## Production facts

- **SSH:** `ssh firstchurch` (HostGator). WP root: `~/public_html`. Auth via the
  `church.pem` key pinned in `~/.ssh/config`.
- **Versions:** WordPress 7.0, PHP 8.2 (web) / 8.3 (CLI), WP-CLI 2.9.
- **WP-Cron** runs from a **real crontab every 15 min** (the WP loopback is blocked, so
  `DISABLE_WP_CRON` is set). Don't "fix" cron by re-enabling the loopback.
- **Caching:** the host's bundled must-use **Endurance Page Cache** is the active page cache.
  After a deploy that changes rendered output, purge it:
  `ssh firstchurch "cd ~/public_html && wp cache flush"`.
- **MCP server:** `wp-json/mcp/mcp-adapter-default-server` — two identities: `mcp-client`
  (read) and `mcp-editor` (scoped writes). Details in
  `wp-content/mu-plugins/firstchurch-mcp-abilities.php`.
- **Backups:** UpdraftPlus, monthly, to Google Drive (DB/content are not in git).
- **Note:** the same `public_html` also serves `paxchristiyoga.org/` (a separate site) —
  mirrored locally but not ours.

---

## Local dev wiring (DDEV)

- Provider **OrbStack**; `php=8.3`, `webserver=apache-fpm` (HostGator parity),
  `database=mariadb:10.11`, `performance_mode=none` (direct mount).
- DDEV manages `wp-config.php`; the `wpqg_` table prefix comes from
  `web_environment: DB_PREFIX=wpqg_`; URLs rewrite to `*.ddev.site` at runtime (no DB
  search-replace).

---

## More

- [`CLAUDE.md`](./CLAUDE.md) — the same model in instruction form, for AI coding agents.
- [`ops/sync/ownership.md`](./ops/sync/ownership.md) — the authoritative push/pull boundary.
- [`wp-content/themes/maranatha-child/README.md`](./wp-content/themes/maranatha-child/README.md)
  — theme internals + Tailwind build.
