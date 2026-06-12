# First Church Seattle — the theme

Standalone first-party WordPress theme for
[firstchurchseattle.org](https://firstchurchseattle.org). No parent theme, no
jQuery, one compiled stylesheet, self-hosted fonts, native ES-module islands.
(It grew out of — and in 2026 fully replaced — the third-party Maranatha parent
+ child pair; the record and cutover runbook are in
`ops/docs/theme-independence.md`.)

## What's here

```
firstchurch/
├── style.css                       # WP theme metadata (frontmatter only)
├── functions.php                   # asset versioning, inc/ autoload, stylesheet enqueue, skip link
├── front-page.php                  # homepage: hero (fcs_front_hero option) + bands
├── page.php · single.php · index.php · 404.php
├── single-fce_event.php            # event detail (Happenings spine)
├── header.php                      # <head> + sticky header (nav/search/mobile) + banner
├── footer.php                      # 3-column footer, inline-SVG socials, © bar
├── inc/                            # one PHP file per concern (autoloaded alphabetically)
│   ├── setup.php                   # theme supports, menu location, content width
│   ├── scripts.php                 # ES-module islands via the Script Modules API
│   ├── theme-compat.php            # shared template tags (fcs_page_title, fcs_has_content)
│   └── …                           # announcements-cta, redirects, schema, static-map, people-display, …
├── partials/                       # card, header-banner, staff-directory, home sections
├── page-templates/                 # blog, child-pages, people, width-*, events (spine), worship-live
├── templates/                      # firstchurch-people display (staff archive + profile)
├── assets/
│   ├── src/                        # THE stylesheet source (Tailwind v4): input → base/chrome/
│   │                               #   footer/components/content/home/events/extras
│   ├── tailwind.css                # BUILT output (gitignored; built on deploy / pulled)
│   ├── fonts/                      # self-hosted Raleway (variable) + Lato woff2
│   ├── logo-white.png · map.webp   # brand/static assets shipped with the theme
│   ├── editor.css                  # minimal block-editor styles
│   ├── happenings-block.js         # classic block-editor script (global wp.*)
│   └── js/                         # buildless ES modules
│       ├── boot.js                 # entry: runs the islands on DOM-ready
│       └── islands/                # nav (header), skip-link, worship-live
├── tests/                          # Vitest unit tests (run in CI)
├── e2e/                            # Playwright specs (LOCAL ONLY — need DDEV)
├── package.json / package-lock.json # pinned dev toolchain: Tailwind + Biome/Vitest/Playwright
├── biome.json · vitest.config.js · playwright.config.js
└── build-css.sh                    # ./build-css.sh [--watch]
```

> Everything from `package.json` down (the JS toolchain, `tests/`, `e2e/`,
> config) is **dev-only** and excluded from deploy in `ops/deploy.sh` —
> production runs no build step. Everything else in `assets/` ships, including
> the built `assets/tailwind.css` (compiled on deploy, not committed).

## CSS

`assets/tailwind.css` is **built, not committed** (gitignored). Source of truth
is `assets/src/` — `input.css` declares the design tokens (`@theme`) and imports
the part files; `base.css` holds the `--fcs-*` semantic palette (light + dark
via `prefers-color-scheme`), fonts, and element defaults. Build:

```sh
./build-css.sh           # once   (host Node too old? use:
./build-css.sh --watch   # watch   ddev exec "cd wp-content/themes/firstchurch && ./build-css.sh")
```

How each environment gets the compiled file:

- **Production:** the CD workflow (`.github/workflows/deploy.yml`) runs
  `build-css.sh` on the runner and rsyncs the result up — HostGator has no Node.
- **Local dev:** `ddev pull-prod` rsyncs prod's built copy down, so the mirror
  shows the same CSS with no Node. When **editing styles**, build locally
  instead (the next pull replaces your local artifact with prod's).

Conventions: every colour routes through the `--fcs-*` tokens (dark mode is one
token flip, no per-component dark rules); components are plain CSS classes
(`fcs-…`); Tailwind utilities are used directly in template markup and scanned
from `**/*.php` + `assets/**/*.js`.

## First-party JavaScript (buildless ES modules)

Front-end JS is **native ES modules** via the WordPress Script Modules API
(`inc/scripts.php`). No bundler; WordPress prints the import map + mtime-versioned
URLs and defers them. The pattern is **progressive-enhancement islands**: each
module self-guards on the markup it needs, so `boot.js` calls them
unconditionally and an island no-ops where its slot is absent.

**Add an island:**

1. Write `assets/js/islands/<name>.js` exporting a `mount…(doc, …)` function
   that guards on its slot; export pure logic for unit testing.
2. Register it in `inc/scripts.php` as `@firstchurch/<name>` and add it to the
   `@firstchurch/boot` dependency array (that's what puts it in the import map
   with a versioned URL).
3. Import + call it in `assets/js/boot.js`.

**Dev toolchain** (dev-only, never shipped):

```sh
npm install          # Vitest, Playwright, Biome
npm test             # Vitest unit tests (happy-dom)  — runs in CI
npm run lint         # Biome lint + format check       — runs in CI
npm run lint:fix     # Biome autofix
npm run e2e:install  # one-time: Playwright browser
npm run e2e          # Playwright specs against DDEV   — LOCAL ONLY
```

**Testing split:** pure logic and DOM-shaping → **Vitest** (`tests/`, in CI).
Real-browser behavior → **Playwright** (`e2e/`), local-only against DDEV
(defaults to `https://firstchurchseattle.ddev.site:8843`; override with
`PLAYWRIGHT_BASE_URL`). The Playwright browser must run on the **host** — the
DDEV container lacks the browser's system libraries.

The block-editor script `assets/happenings-block.js` is a separate **classic**
script (depends on the editor's global `wp`); smoke-test editor changes by hand.

## Content the theme reads

- **`fcs_front_hero` option** — the homepage hero (title/content/image/links).
  It's content, not code (seasonal notices live there). Seeded once by
  `ops/bin/seed-front-hero.php`; edit via wp-cli (`wp option patch …`).
- **Happenings spine** (`firstchurch-happenings`) — events lists/calendar/cards.
- **`firstchurch-people`** — staff directory + profiles.
All reads fail soft if a plugin is inactive.

## Deploying

Merge to `main` → CI/CD ships this directory (compiling `tailwind.css` on the
runner). Manual fallback `ops/deploy.sh` requires a local build first — the
script refuses to mirror a missing `tailwind.css` so `--delete` can't wipe
prod's copy.

## Versioning

Asset `?ver=` strings come from each file's mtime via `fcs_asset_version()` —
no version constant to bump. Deploys rsync timestamps from a fresh CI checkout,
so every shipped file gets a new `?ver=`; the `asset version bump` CI guard
exempts this theme accordingly.
