# Composer dependencies on prod — build in CD, ship the artifact

**Status:** Spike / proposal. Wired end-to-end for **one** plugin (`firstchurch-enews`,
the Mailchimp SDK) as the worked example; not yet adopted as policy.
**Date:** 2026-06-10
**Scope:** how a custom plugin can use a real third-party Composer library on HostGator,
which runs no Composer.

---

## 1. The reframe

The rule was never really "no Composer." It's **"prod is a dumb rsync target that runs no
build."** And we already relaxed that for the front end: CD compiles `tailwind.css` from source
and `deploy.sh` just rsyncs the result (`ops/docs/ci-cd.md` — *"deploy.sh stays Node-free; the
build is a workflow step… prod serves this build"*). `node_modules` is gitignored and built
fresh in CI; only the artifact ships.

**Composer fits that exact mold.** You don't need Composer *on HostGator* — you need a built
`vendor/` to exist there. Build it in CD, rsync it, and prod still runs nothing new.

## 2. The model

```
  CI/CD (GitHub Actions)                                  HostGator (no build)
  ┌──────────────────────────────────────────┐
  │ composer install --no-dev                 │           plugin/ ← rsync of dist/
  │ php-scoper add-prefix → dist/             │  ──rsync──▶  (scoped vendor + source)
  │ composer dump-autoload (in dist/)         │
  └──────────────────────────────────────────┘
        ▲ same shape as: build-css.sh → tailwind.css → rsync
```

- **`vendor/` is never committed** (gitignored, like `node_modules`); `composer.lock` *is*
  (reproducible).
- The plugin's shipped form is its **built `dist/`** — scoped deps + its own source — not the
  raw tree. `deploy.sh` rsyncs `dist/`; CD runs the build first; a manual deploy must too
  (it errors loudly if `dist/` is absent).

## 3. The one real gotcha: collisions → PHP-Scoper

Per-plugin `vendor/` means if two plugins each pull a popular library — **Guzzle** is the
classic — both autoloaders declare the same classes and you get a fatal `Cannot redeclare
class`. We have *already hit this shape*: `firstchurch-events` vendors `rlanvin/php-rrule` and
had to **hand-rename its namespace** because Church Theme Content ships a second copy.

**PHP-Scoper automates that rename.** At build time it moves a plugin's whole dependency tree
into a private prefix and rewrites the references — including the `use` statements in the
plugin's *own* source — so the shipped copy can't collide with anyone else's:

```
source:  use MailchimpMarketing\ApiClient;
built:   use FirstChurch\ENews\Vendor\MailchimpMarketing\ApiClient;   ← php-scoper did this
```

Our source stays clean (real namespaces); the build is where isolation happens. Verified for
the worked example: the shipped `dist/` exposes `FirstChurch\ENews\Vendor\GuzzleHttp\Client`
and **no** global `GuzzleHttp\Client` / `MailchimpMarketing\ApiClient`, while our own
`FirstChurch\ENews\Email` is untouched.

## 4. The recipe (per plugin that needs a library)

1. `composer.json` — declare the real dep under `require` (e.g. `mailchimp/marketing`).
2. `scoper.inc.php` — `prefix => 'FirstChurch\<Plugin>\Vendor'`, finders over `vendor` (ALL
   files, not just `*.php` — `installed.json` must be copied or the scoped autoloader can't
   find its packages), plus the plugin's own `src`/`inc`. `exclude-namespaces` keeps our
   first-party namespace.
3. `build.sh` —
   ```
   composer install --no-dev --optimize-autoloader --classmap-authoritative
   php-scoper add-prefix --output-dir=dist --force
   composer dump-autoload --working-dir=dist --classmap-authoritative --no-dev
   ```
   php-scoper is a **phar** (not a Composer dep — it would fight `--no-dev`); CD downloads it.
4. **bootstrap** — `if ( file_exists(__DIR__.'/vendor/autoload.php') ) require it;` so the SDK
   autoloads (present in the shipped `dist/`, and in a dev `composer install`).
5. **deploy.sh** — rsync the plugin's `dist/` instead of its source.
6. **deploy.yml** — a build step before the rsync (download php-scoper, run `build.sh`),
   mirroring the Tailwind step. Add it right after the Tailwind compile:
   ```yaml
         - name: Set up PHP + Composer
           uses: shivammathur/setup-php@v2
           with:
             php-version: '8.2'
             tools: composer
         - name: Build PHP-scoped plugin deps (prod serves this build)
           run: |
             curl -fsSL -o /tmp/php-scoper.phar \
               https://github.com/humbug/php-scoper/releases/download/0.18.18/php-scoper.phar
             PHP_SCOPER=/tmp/php-scoper.phar wp-content/plugins/firstchurch-enews/build.sh
   ```
   > This snippet is **not committed by the spike** — the CI bot lacks GitHub's `workflows`
   > permission, so a human applies this one edit to `.github/workflows/deploy.yml`.
7. **.gitignore** — ignore `dist/` (and any local phar).

## 5. What this is NOT

- **Not** `composer install` over SSH on HostGator at deploy time. Shared hosting + packagist
  egress + PHP memory limits + a non-atomic build on the *live* server is the model people
  regret. Keep prod build-free.
- **Not** a site-wide single-vendor (Bedrock) restructure. That's the cleaner end-state for a
  collision-free *shared* autoloader, but it relays the whole WP layout — far more than one SDK
  warrants now. Per-plugin scoped builds get us there incrementally.
- **Not** the new default. Most plugins have zero third-party deps and should stay pure `src/`
  + explicit `require` — simpler and collision-proof. Composer-on-prod is **opt-in per plugin
  that genuinely needs a library.**

## 6. Worked example: `firstchurch-enews` + the Mailchimp SDK

The Mailchimp push (step 6.5) was originally a hand-rolled `wp_remote_request` client —
deliberately, *because* of the no-Composer constraint. This spike swaps it for the official
`mailchimp/marketing` SDK behind the build above, so you can compare:

- **Source diff is small:** `inc/mailchimp.php` now does `$client->campaigns->create(...)` /
  `->update(...)` / `->setContent(...)` instead of three `wp_remote_request` calls. The pure
  `src/Mailchimp.php` helpers **survive unchanged** (datacenter, payload shaping, error
  parsing) — they just feed the SDK now. The unit suite is still green (19/19).
- **Cost:** the prod-only dependency tree is **~3.2 MB / ~160 PHP files** (Guzzle + PSR + the
  generated SDK), built in CD, for what is three API calls.
- **Trade-off vs. the hand-rolled client:** the SDK owns transport/auth/retries and is less
  code to maintain, but it bypasses WordPress's HTTP layer (Guzzle, not `wp_remote_request`)
  and pulls a Guzzle tree that *must* be scoped. For three stable endpoints the hand-rolled
  client is arguably still the lighter choice; the SDK wins once we use more of Mailchimp
  (audiences, automations, reporting).

## 7. Recommendation

Adopt the **capability** (this build-in-CD + scoping path) so `composer require` is a real
option — but keep it **opt-in**. For the Mailchimp case specifically, either client is
defensible; pick the SDK if we expect to grow the integration, keep the hand-rolled one if it
stays a single draft-push. Once adopted, `firstchurch-events`' hand-renamed php-rrule can be
retired onto the same path.

### Open items before this is policy
- A CI guard that `build.sh` runs clean (a `dist/` smoke), so a broken scope can't reach deploy.
- `shivammathur/setup-php` pins PHP 8.2 in CD to match prod (web) — confirm the SDK runs on
  PHP 8.2/8.3 there.
- Decide whether `dist/` should also be byte-reproducible (lock php-scoper + composer versions).
