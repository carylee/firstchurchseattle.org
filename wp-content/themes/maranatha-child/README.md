# Maranatha Child — First Church Seattle

WordPress child theme of [Maranatha](https://churchthemes.com/themes/maranatha) v2.6.
Adds the site-specific mobile UX, layout polish, and the custom `/worship/live/`
page template for [firstchurchseattle.org](https://firstchurchseattle.org).

## What's here

```
maranatha-child/
├── style.css                       # WP theme metadata (frontmatter only)
├── functions.php                   # enqueues, skip-link, parent-style fix
├── partials/
│   └── header-banner.php           # override: clean compact maroon banner
├── page-templates/
│   └── page-worship-live.php       # Template Name: Worship Live (Custom)
├── assets/
│   ├── mobile.css                  # hand-written CSS (drawer, tap targets, polish)
│   ├── tailwind.css                # COMPILED Tailwind v4 output (committed)
│   └── src/input.css               # Tailwind v4 source
├── bin/
│   ├── tailwindcss                 # standalone binary (gitignored — see bin/README.md)
│   └── README.md
└── build-css.sh                    # ./build-css.sh [--watch]
```

## Quickstart (development)

```sh
# One-time: install Tailwind CLI binary (see bin/README.md for other platforms)
curl -sLO https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-macos-arm64
mv tailwindcss-macos-arm64 bin/tailwindcss && chmod +x bin/tailwindcss

# Build once
./build-css.sh

# Or watch + rebuild during development
./build-css.sh --watch
```

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
