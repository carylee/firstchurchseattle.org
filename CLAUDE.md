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

The normal path is **automatic via CI/CD: merging to `main` deploys.** You do *not*
need to run anything by hand or SSH into prod. The `.github/workflows/deploy.yml`
workflow runs the same `ops/deploy.sh` for you — it triggers when the **CI** workflow
finishes *successfully* on `main` (a red build never deploys), checks out the exact
commit CI validated, and rsyncs to HostGator. So the deliverable for a code change is a
**merged PR**, not a manual deploy. (The `production` GitHub Environment can add a
required-reviewer approval gate before the rsync fires — check there if a deploy is
"stuck".) Full notes: `ops/docs/ci-cd.md`.

Running `ops/deploy.sh` yourself is the **manual fallback** — for hotfixes, when CI is
down, or to dry-run before merging. It needs `ssh firstchurch` access, which the CI
runner has via secrets but a fresh Claude Code web session does **not**:
```bash
ops/deploy.sh -n           # dry run
ops/deploy.sh              # deploy
```
You can also trigger the deploy workflow manually (`workflow_dispatch`, with an optional
dry-run input) without merging.

Both directions honor one ownership model so they can't conflict — see `ops/sync/ownership.md`.

> **⚠️ A new plugin/theme is NOT deployed until you add it to `ops/deploy.sh`.**
> The deploy is an **explicit allowlist of paths**, not a wildcard over `wp-content/` —
> it rsyncs only the specific themes/plugins/files named in `ops/deploy.sh`. Adding a new
> tracked plugin directory (e.g. `wp-content/plugins/firstchurch-foo/`) and merging it does
> **nothing** on prod until you add a matching `rsync` line to `deploy.sh`. The CD pipeline
> just runs `deploy.sh`, so it will go **green while silently skipping your new code** — a
> passing deploy is *not* proof your plugin shipped.
>
> **Whenever you create a new custom plugin, add it to `ops/deploy.sh` in the same PR**
> (mirror with `--delete` if fully ours; exclude dev-only artifacts like `vendor/`/`tests/`
> as `firstchurch-breeze-forms` does). Then remember files alone aren't enough: a new plugin
> also needs `ssh firstchurch 'cd ~/public_html && wp plugin activate <slug>'` (and any one-time
> seed/migration) on prod. Verify after deploy: `ssh firstchurch 'ls ~/public_html/wp-content/plugins/'`.
>
> **⚠️ wp-cli on prod must run from `~/public_html`.** SSH lands you in `~` (the HostGator home
> dir), which has **no WordPress install** — a bare `ssh firstchurch 'wp …'` fails with
> "This does not seem to be a WordPress installation." Always `cd ~/public_html &&` first (or
> pass `--path=~/public_html`). This applies to *every* remote `wp` invocation — plugin
> activation, menu edits, migrations, option sets.

## How to work on the site

| You want to… | Do this |
|---|---|
| Change the **theme / a custom plugin / the MCP mu-plugin** | Edit it in place under `wp-content/…` → preview live at `*.ddev.site` → commit → open a PR → **merge to `main` (CI/CD deploys; no manual step)**. **Theme changes go in `maranatha-child` only** — never edit the `maranatha` parent (see below). |
| Edit **content** (events, posts, sermons, announcements) | That's data, edited on prod via the MCP server; `ddev pull-prod --db-only` to pull it down |
| **Refresh** your local copy of prod | `ddev pull-prod` |
| Run **wp-cli** locally | `ddev wp <args>` |
| Open a **DB shell** | `ddev mysql` |

**Discipline:** edit Class-A code *here and deploy it* — don't edit the theme/plugins
directly on prod. The pull excludes tracked code, so a direct-prod edit won't come back and
the next deploy will overwrite it. (This is the drift the old two-repo setup suffered.)

**Never edit the `maranatha` parent theme.** It's a third-party theme (ChurchThemes.com),
vendored into git only to pin the exact version the site runs and to catch drift — not
because it's ours to change. Every theme customization belongs in `maranatha-child`
(override templates, enqueue CSS/JS, hook filters there). Editing `maranatha` directly would
be silently lost the next time the parent theme is updated. If the child theme can't express
a change, add a hook/override rather than patching the parent.

**Commits:** prefer small, self-contained commits, one per milestone. The test is
cherry-pickability — could someone lift this commit onto another branch on its own and have
it make sense? If so, it's the right size. Commit each distinct fix/feature separately rather
than batching unrelated changes; keep theme version bumps with the change that needs them.

## What's tracked vs. mirrored

```
firstchurchseattle.org/                 ← git repo + DDEV project
├── CLAUDE.md, README.md, .gitignore    ← tracked
├── .ddev/                              ← tracked (config + the pull-prod command)
├── ops/                               ← tracked — see below
├── wp-content/
│   ├── themes/maranatha/               ← TRACKED  (vendored parent theme — DO NOT EDIT)
│   ├── themes/maranatha-child/         ← TRACKED  (our theme — all changes go here)
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
| `ops/scripts/check-deploy-coverage.sh` | CI guardrail: fails if a tracked plugin/theme/mu-plugin isn't wired into `deploy.sh` |
| `ops/scripts/check-pull-protection.sh` | CI guardrail: fails if tracked custom code isn't excluded from `ddev pull-prod` |
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
- Local ports are non-standard (caddy owns 80/443 on this box): DDEV router is
  **http :8800 / https :8843**, so the site is `https://firstchurchseattle.ddev.site:8843`.

### Serving the local site over Tailscale (already set up — don't reinvent)

The **`ddev-tailscale-router`** add-on is installed (`.ddev/commands/host/tailscale`,
`.ddev/*tailscale*`). It runs `tailscaled` **inside the web container** as its own tailnet
node `firstchurchseattle` (distinct from this host's `nuc` node) and fronts the container's
port 80 via `tailscale serve`. It's authenticated and the serve config persists, so the
local DDEV site is **normally already reachable** tailnet-only at:

> **`https://firstchurchseattle.weasel-barley.ts.net`** (valid Tailscale TLS; DDEV rewrites
> URLs to this host so assets/links resolve).

Manage it with the add-on's host command — **not** raw `tailscale serve` on the host:
- `ddev tailscale url` — print the URL · `ddev tailscale proxystat` — show the serve mapping
- `ddev tailscale launch` — open in browser · `ddev tailscale stop` — tear down the share
- `--public` (on `share`/`launch`) switches to a public Funnel (exposes to the internet —
  don't, unless intended).

> **Gotcha — after `ddev stop`/`start`, re-share with `ddev tailscale share --port=80`.**
> `ddev stop` logs the container's tailnet node out, so the share drops. The `--port=80` is
> **required**: the add-on defaults to `$DDEV_ROUTER_HTTP_PORT` (8800 here, the remapped
> router port), but inside the web container nothing listens on 8800 — apache is on **80**.
> `TS_AUTHKEY` (for re-auth) lives in `~/.zshrc`, not committed.
