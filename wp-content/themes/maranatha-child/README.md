# Maranatha Child — First Church Seattle

WordPress child theme of [Maranatha](https://churchthemes.com/themes/maranatha) v2.6.
Adds the site-specific mobile UX, layout polish, and the custom `/worship/live/`
page template for [firstchurchseattle.org](https://firstchurchseattle.org).

## What's here

```
maranatha-child/
├── style.css                       # WP theme metadata (frontmatter only)
├── functions.php                   # enqueues, skip-link, parent-style fix
├── inc/                            # one PHP file per concern (kept out of functions.php)
│   ├── scripts.php                 # enqueue first-party JS as ES modules (Script Modules API)
│   └── …                           # font-optimization, footer-map, sermon-structured-data, …
├── partials/
│   └── header-banner.php           # override: clean compact maroon banner
├── page-templates/
│   └── page-worship-live.php       # Template Name: Worship Live (Custom)
├── assets/
│   ├── mobile.css                  # hand-written CSS (drawer, tap targets, polish)
│   ├── tailwind.css                # COMPILED Tailwind v4 output (committed; CI-verified)
│   ├── src/input.css               # Tailwind v4 source (the source of truth)
│   ├── happenings-block.js         # classic block-editor script (global wp.*)
│   └── js/                         # first-party ES modules (buildless — see below)
│       ├── boot.js                 # entry module: runs the islands on DOM-ready
│       └── islands/                # progressive-enhancement islands
│           ├── skip-link.js        # injects the #main-content focus target
│           └── worship-live.js     # Sunday "Live now / Next service" status line
├── tests/                          # Vitest unit tests (run in CI)
├── e2e/                            # Playwright specs (LOCAL ONLY — need DDEV)
├── package.json / package-lock.json # pinned dev toolchain: Tailwind 4.3.0 + Biome/Vitest/Playwright
├── biome.json · vitest.config.js · playwright.config.js
└── build-css.sh                    # ./build-css.sh [--watch]
```

> Everything from `package.json` down (the JS toolchain, `tests/`, `e2e/`,
> config) is **dev-only** and excluded from deploy in `ops/deploy.sh` — like the
> Tailwind binary, production runs no build step. Only the committed runtime
> assets (`assets/js/**`, `assets/*.css`) ship.

## Quickstart (development)

The Tailwind toolchain is pinned in `package.json` (no manually-downloaded
binary). You need Node; `build-css.sh` installs deps on first run.

```sh
# Build once (runs `npm ci` automatically if node_modules is missing)
./build-css.sh

# Or watch + rebuild during development
./build-css.sh --watch
```

`assets/tailwind.css` is the **committed compiled artifact** — its source of
truth is `assets/src/input.css`. CI rebuilds it and fails if the two drift
(`ops/scripts/check-tailwind-build.sh`), so always commit a fresh `./build-css.sh`
output alongside any `input.css` change. (`node_modules/` is gitignored.)

## First-party JavaScript (buildless ES modules)

Our front-end JS is **native ES modules**, loaded via the WordPress Script
Modules API (`wp_register_script_module` / `wp_enqueue_script_module` in
`inc/scripts.php`). There is **no bundler** — the browser loads the modules
directly and WordPress prints the import map + cache-busted (`?ver=`) URLs and
defers them. Like the Tailwind flow, **nothing is built on prod.**

The pattern is small **progressive-enhancement islands**. Each island is a
module that self-guards on the markup it needs (e.g. its `[data-island="…"]`
slot), so `boot.js` can call them unconditionally and an island simply no-ops on
pages that don't use it. Markup ships working without JS; islands enhance it.

**Add an island:**

1. Write `assets/js/islands/<name>.js` exporting a `mount…(doc, …)` function
   that guards on its slot. Put any pure logic in the same file and export it so
   it's unit-testable without a DOM.
2. Register it in `inc/scripts.php` as `@firstchurch/<name>` and add it to the
   `@firstchurch/boot` dependency array (that's what puts it in the import map
   with a versioned URL — keep imports inside `assets/js/` going through the
   registered specifier, not relative paths, so nothing ships unversioned).
3. Import + call it in `assets/js/boot.js`.
4. Bump `FCS_CHILD_VERSION`.

**Dev toolchain** (one-time `npm install`; dev-only, never shipped):

```sh
npm install          # Vitest, Playwright, Biome
npm test             # Vitest unit tests (happy-dom)  — runs in CI
npm run lint         # Biome lint + format check       — runs in CI
npm run lint:fix     # Biome autofix
npm run e2e:install  # one-time: Playwright browser
npm run e2e          # Playwright specs against DDEV   — LOCAL ONLY
```

**Testing split:** pure logic and DOM-shaping → **Vitest** (`tests/`, in CI).
Real-browser behavior → **Playwright** (`e2e/`). Playwright is **local-only**: it
needs a running WordPress, which CI deliberately does not provision, so start
DDEV first (`ddev start`) and point at it (defaults to
`https://firstchurchseattle.ddev.site:8843`; override with `PLAYWRIGHT_BASE_URL`).

The block-editor script `assets/happenings-block.js` is a separate, **classic**
script (not a module): it depends on the global `wp` the editor provides. It's
lint-gated by Biome; smoke-test editor changes by hand in DDEV (the editor needs
admin auth, so it isn't part of automated CI).

## Deploying to production

Production does NOT run any build step. Workflow:

1. Build locally: `./build-css.sh`
2. Verify `assets/tailwind.css` updated and looks right
3. Commit the change (the compiled CSS is intentionally tracked)
4. Copy this whole `maranatha-child/` directory to production at
   `wp-content/themes/maranatha-child/` (scp / cPanel File Manager / rsync)
5. In wp-admin → Appearance → Themes → activate "Maranatha Child" (first time)
6. For `/worship/live/`: in wp-admin → Pages → Worship Live → Page Attributes
   → Template → select "Worship Live (Custom)"

Hard-refresh once in a browser to bust any CDN/page-cache. Subsequent edits
just need a version bump in `functions.php` (`FCS_CHILD_VERSION`).

## Important non-obvious things

1. **Parent style enqueue fix**. The parent Maranatha enqueues its main
   stylesheet under the handle `maranatha-style` using `get_stylesheet_uri()`
   — which when a child is active resolves to the CHILD's empty style.css,
   not the parent's 6500-line one. Without the dequeue/re-enqueue in
   `functions.php`, the site loads completely unstyled.

2. **Tailwind utilities are unlayered on purpose**. The default Tailwind v4
   setup uses `@import "tailwindcss/utilities.css" layer(utilities)`, but
   unlayered parent-theme rules (`h1`, `a`, `p` element selectors) always
   beat layered rules — meaning utility classes silently lose. Our
   `assets/src/input.css` imports utilities WITHOUT a layer wrapper so they
   participate in normal cascade.

3. **Component CSS (`.btn-primary` / `.cta-tile` / `.card-action`) is plain
   CSS, not `@utility`**. Same reason as above — `@utility` puts rules
   inside `@layer utilities`, which loses to unlayered parent styles.

4. **Banner top padding clears the fixed parent header**. `partials/header-banner.php`
   uses `pt-24 sm:pt-32` to push the `<h1>` below the parent theme's
   `position: fixed` `#maranatha-header-top` (which is ~80–135 px tall).

## Versioning

`FCS_CHILD_VERSION` in `functions.php` is the asset cache-bust string.
Bump it whenever you change `mobile.css`, `tailwind.css`, or any enqueued
asset, so visitors don't see stale CSS.
