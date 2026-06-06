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
- **breeze-forms** — `composer install` + `vendor/bin/phpunit` in
  `wp-content/plugins/firstchurch-breeze-forms`.

`firstchurch-connection-card` has no test suite yet, so it's lint-only. Add a
`tests/` dir + `composer.json` there and it picks up the same pattern.

## CD — [`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml)

Runs `ops/deploy.sh` from a runner. Triggered by:

- **`workflow_run`** — automatically, *after* the CI workflow finishes
  **successfully on `main`**. A red CI run never deploys.
- **`workflow_dispatch`** — manually from the Actions tab, with a **`dry_run`**
  checkbox that runs `ops/deploy.sh -n` (rsync preview, no changes).

Deploys are serialized (`concurrency: deploy-production`) and run in the
`production` environment so you can require manual approval (see below).

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

3. **Branch protection (recommended)** — Settings → Branches → protect `main` →
   *require status checks to pass before merging* and select the CI jobs. This
   makes "merged to main" mean "CI-green," which is what the deploy gate assumes.

### Notes

- The workflow writes the key + an `~/.ssh/config` `firstchurch` host block at
  runtime; `ops/deploy.sh` connects through that alias unchanged.
- `StrictHostKeyChecking accept-new` trusts the host on first connect. To pin
  it instead, add the host's public key to `~/.ssh/known_hosts` in the
  *Configure SSH* step (e.g. from a `DEPLOY_KNOWN_HOSTS` secret).
- To deploy a hotfix without a merge: Actions → *Deploy to production* → *Run
  workflow* (optionally with **dry_run** first to preview).
