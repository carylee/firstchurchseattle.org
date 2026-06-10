# CI / CD

Two GitHub Actions workflows automate the **push** half of the sync boundary
(`ops/deploy.sh`). The pull half (`ddev pull-prod`) stays manual.

```
PR ─▶ CI (lint + PHPUnit) ─┐
                           ├─ merge to main ─▶ CI on main ─▶ (green) ─▶ Deploy ─▶ HostGator
manual dispatch ───────────┘                                  └─ (red) ─▶ no deploy
```

## CI — [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml)

Runs on every PR into `main` and on pushes to `main`. No WordPress, no DB —
our tracked code is a child theme plus a few plugins, and the breeze-forms
suite is standalone (its `tests/bootstrap.php` shims the handful of WP escaping
helpers it touches).

- **lint** — `php -l` on every tracked `*.php`, on PHP 8.2 and 8.3 (prod runs
  8.2 web / 8.3 CLI).
- **PHPUnit + composer audit (per plugin)** — the matrices are **discovered,
  not hand-listed**: a `discover` job scans tracked files and fans out one
  PHPUnit job per plugin with a `phpunit.xml.dist` (`composer install` +
  `vendor/bin/phpunit`) and one `composer audit` per plugin with a
  `composer.lock`. A new plugin's suite runs the moment those files land — no
  `ci.yml` edit, so tests can't be silently skipped. A tracked plugin with no
  suite at all is surfaced as a `::warning::` (lint-only), not a failure.
- **tailwind builds** — a smoke test: compiles `assets/src/input.css` with the
  pinned toolchain (`build-css.sh`) and fails if the source no longer builds.
  `wp-content/themes/maranatha-child/assets/tailwind.css` is **not committed** (it's
  built on deploy — see below), so there's nothing to diff against; this just catches
  a broken `input.css` at PR time instead of at deploy time.

## CD — [`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml)

Runs `ops/deploy.sh` from a runner. Triggered by:

- **`workflow_run`** — automatically, *after* the CI workflow finishes
  **successfully on `main`**. A red CI run never deploys.
- **`workflow_dispatch`** — manually from the Actions tab, with a **`dry_run`**
  checkbox that runs `ops/deploy.sh -n` (rsync preview, no changes).

Deploys are serialized (`concurrency: deploy-production`) and run in the
`production` environment so you can require manual approval (see below).

### Tailwind is built on deploy (not committed)

The child theme's `assets/tailwind.css` is a **built artifact** (source:
`assets/src/input.css`, pinned toolchain in `package.json`) and is **gitignored —
not committed**. **Production never builds** (HostGator has no Node), so the
artifact has to be produced *somewhere* before it ships:

- **CD** is where it happens. The `deploy` job runs `build-css.sh` on the runner
  right before the rsync, so prod always serves a freshly compiled file. This is
  load-bearing, not defense-in-depth — it's the only thing that puts a current
  `tailwind.css` on prod.
- **`ops/deploy.sh` stays a pure rsync** (the build is a separate workflow step,
  not in the script). A manual/dev deploy therefore must build `./build-css.sh`
  first; the script *guards* on the file's presence and refuses to run if it's
  missing, so its `--delete` theme mirror can't wipe prod's copy.
- **Local dev** gets it without Node: `ddev pull-prod` rsyncs prod's built
  `tailwind.css` down (one extra single-file rsync, since the theme dir is
  otherwise protected from the pull). When you're editing styles, run
  `./build-css.sh --watch` locally instead.

The CD build step in `deploy.yml` (a `setup-node` + `build-css.sh` pair before
*Configure SSH*): `build-css.sh` runs `npm ci` (when `node_modules` is absent, as
on a fresh runner) then `npx tailwindcss … --minify`, writing `assets/tailwind.css`
into the checked-out tree that `deploy.sh` then rsyncs.

### One-time setup

1. **Secrets** — Settings → Secrets and variables → Actions:

   | Secret | Value |
   |---|---|
   | `DEPLOY_SSH_KEY` | A private key authorized on the HostGator account (the public half in the account's `~/.ssh/authorized_keys`). Consider a **deploy-only key** rather than reusing `church.pem`. |
   | `DEPLOY_HOST` | HostGator hostname/IP for the `firstchurch` account |
   | `DEPLOY_USER` | SSH username |
   | `DEPLOY_PORT` | SSH port (optional; defaults to `22`) |

   Host/user/port live in secrets (not the workflow file) so prod connection
   details aren't committed.

2. **Approval gate (recommended)** — Settings → Environments → **production** →
   add yourself as a *required reviewer*. Each deploy then pauses for one click
   before it touches prod.

3. **Branch protection (enabled)** — `main` requires the **CI OK** status check
   before merging. That's the aggregate `ci-ok` job in `ci.yml` — require *only*
   this one, never the per-plugin matrix jobs (their names change as plugins
   come and go). This makes "merged to main" mean "CI-green," which is what the
   deploy gate assumes. `enforce_admins` is off, so an admin can still push
   directly in an emergency — the protection guards the default path, not the
   hotfix escape hatch.

### Notes

- The workflow writes the key + an `~/.ssh/config` `firstchurch` host block at
  runtime; `ops/deploy.sh` connects through that alias unchanged.
- `StrictHostKeyChecking accept-new` trusts the host on first connect. To pin
  it instead, add the host's public key to `~/.ssh/known_hosts` in the
  *Configure SSH* step (e.g. from a `DEPLOY_KNOWN_HOSTS` secret).
- To deploy a hotfix without a merge: Actions → *Deploy to production* → *Run
  workflow* (optionally with **dry_run** first to preview).
