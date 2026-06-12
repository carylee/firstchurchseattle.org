# Theme independence — ✅ complete (pending prod cutover)

**Done:** `firstchurchseattle.org` runs on **`wp-content/themes/firstchurch/`** — a
standalone first-party theme. The third-party Maranatha parent + `maranatha-child` pair
and the Church Theme Content plugins are fully retired from the codebase. This doc is now
the **record** of what shipped and the **runbook** for the production cutover + cleanup.

---

## Church Theme Content — ✅ decommissioned (2026-06-11)

Both plugins (`church-theme-content` 2.7 + `church-content-pro` 1.3) are **inactive on
prod**. Their types were retired or re-owned first:

| CTC type | Outcome |
|---|---|
| `ctc_sermon` | Retired — all sermon URLs 301 → `/worship/live/` (`inc/redirects.php`); YouTube is the archive. 150 legacy sermon posts remain in the DB, unreachable. |
| `ctc_event` | Replaced by `firstchurch-events` (`fce_event`); spine reads only `fce_event`. 14 unpublished CTC originals remain as rollback insurance. |
| `ctc_person` | **Re-owned in place** by `firstchurch-people` (same type name, same meta — zero migration). Active on prod. |
| `ctc_location` | Retired — single record; address lives in the footer/contact page. |

**Remaining CTC cleanup (see runbook §3):** delete the two plugin dirs from prod, purge
the 14 `ctc_event` drafts once the migration is confirmed stale, optionally export+purge
the unreachable sermon posts.

## The theme — ✅ standalone (PR #99)

What `firstchurch` 1.0.0 is:

- **No parent.** `Template:` line gone; verified locally with the `maranatha` directory
  physically removed — every public surface renders clean (no fatals, no parent assets).
- **One stylesheet.** Tailwind v4 build from `assets/src/` (tokens → base → chrome →
  components → content → home → events → extras) compiled to `assets/tailwind.css`
  (built, not committed; CI builds on the deploy runner). The parent's 7,632-line CSS and
  the child's four override sheets are gone. The `--fcs-*` semantic palette (light + dark)
  carried over intact.
- **Self-hosted fonts.** Raleway variable + Lato latin woff2 in `assets/fonts/` —
  no Google Fonts request. Elusive icon font replaced by inline SVG.
- **First-party chrome.** New sticky header (logo, CSS dropdown nav, full-screen mobile
  panel, search popover) driven by a small vanilla nav island — the parent's
  superfish/meanmenu/matchHeight/tooltipster jQuery stack is gone; the theme enqueues no
  jQuery at all. The breadcrumb strip was retired by design.
- **Standard template hierarchy.** `front-page.php`, `page.php`, `single.php`,
  `index.php` + one card partial replace the parent's `loop.php` dispatcher and
  `content-*` partial system. `comments.php` deleted (zero comments ever).
- **Same-path page templates** so DB assignments carried over: `blog` (/news/),
  `child-pages` (10 section landings), `people` (Staff page; shares a partial with the
  `/staff/` CPT archive), `width-medium`/`width-large`, the two spine event templates
  (now self-contained — the `maranatha_after_content` / `maranatha_content_width` hooks
  are gone; announcements use the theme's own `fcs_after_content`).
- **Homepage** is `front-page.php`: hero from the **`fcs_front_hero` option** (content,
  not code — seasonal notices live there; seed/edit via wp-cli or a future MCP ability),
  then the visit/This-Sunday/happenings + Shared Breakfast partials, then three
  hardcoded navigation bands. The parent's `ctfw-section` widgets are dead data after
  the seed (runbook §2).
- **Compat kept on purpose:** the `.maranatha-button` class is styled as a legacy alias
  (the connection-card + breeze-forms plugins still emit it — follow-up below), and the
  `firstchurch-people` plugin keeps the `ctc_person` type name (deliberate, zero-migration).

## Production cutover runbook

Deploy ships `themes/firstchurch/` alongside the still-active `maranatha-child` (whose
rsync line is gone from `deploy.sh`, so prod's copy sits untouched as rollback). Then:

```bash
# 1. Seed the homepage hero from the legacy widget (one-time).
cat ops/bin/seed-front-hero.php | ssh firstchurch 'cat > /tmp/seed-hero.php && cd ~/public_html \
  && wp eval-file /tmp/seed-hero.php && rm /tmp/seed-hero.php'

# 2. Activate + point the menu at the new theme's location.
ssh firstchurch 'cd ~/public_html && wp theme activate firstchurch \
  && wp menu location assign "Menu 1" header'

# 3. Verify: home, /about/, /news/, a post, /engage/, /upcoming-events/,
#    /events-calendar/, /about/staff-2/, /worship/live/, /worship/prayer/,
#    /connection-card/, search, a 404. Check mobile menu + dark mode.
#    (Cloudflare/page cache may serve stale HTML briefly — purge if needed.)
```

**Rollback:** `wp theme activate maranatha-child` (+ `wp menu location assign "Menu 1" header`)
— files for both old themes remain on prod until cleanup.

## Post-cutover cleanup (after a healthy cycle)

```bash
# Old themes off prod (git history keeps the vendored parent forever).
ssh firstchurch 'cd ~/public_html && wp theme delete maranatha maranatha-child'

# CTC plugin code off prod (data stays; plugins have been inactive since 2026-06-11).
ssh firstchurch 'cd ~/public_html && wp plugin delete church-theme-content church-content-pro'

# Data hygiene (optional, reversible-ish — trash first where possible):
#  - unpublish the dead sermon index pages (IDs 20/26/28/30/32 under /worship/sermons-2/
#    — already 301ed by inc/redirects.php, so this is tidiness, not a fix)
#  - retire /past-events/ and /campus-locations/ (parent-template pages, now contentless)
#    with redirects to /upcoming-events/ and /about/contact-us/
#  - purge the 14 unpublished ctc_event migration originals
#  - delete the dead widget options: widget_ctfw-section + the ctcom-home-sections
#    entry in sidebars_widgets (after confirming fcs_front_hero is seeded)
```

## Follow-ups (tracked, not blocking)

- **Drop `.maranatha-button`** from `firstchurch-connection-card` and
  `firstchurch-breeze-forms` markup (style their own `fcc-submit`/`fcbf-*` classes),
  then delete the alias block in `assets/src/components.css`.
- **MCP hero ability** — small `firstchurch-update-hero` ability so agents can edit
  `fcs_front_hero` (e.g. remove the Pride Sunday line after June 28) without wp-cli.
- **Editor styles** — `assets/editor.css` is deliberately minimal; consider mirroring
  the front-end type ramp in the block editor iframe.
