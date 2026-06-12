# Theme independence — retiring the parent theme

**Goal:** reach a point where `firstchurchseattle.org` runs on `maranatha-child` alone,
with the vendored `maranatha` parent theme removed. This is the endgame the content-type
extraction work was all walking toward — not a big‑bang rewrite, but function‑by‑function
extraction into first‑party code.

This doc is the **map**: what still depends on the parent, who owns each piece, and the
order to retire them. Update it as each dependency falls.

---

## Church Theme Content — ✅ decommissioned

Both third‑party plugins (`church-theme-content` 2.7 + `church-content-pro` 1.3) were
**deactivated 2026-06-11** after all four content types were retired or re‑owned:

| CTC type | State | Replacement |
|---|---|---|
| `ctc_sermon` (+ 5 taxonomies) | **Retired** | All sermon URLs 301 → `/worship/live/` (`maranatha-child/inc/redirects.php`). ~150 published posts remain in DB as orphaned data (no post type). |
| `ctc_event` | **Retired** | `firstchurch-events` (`fce_event`, RRULE‑backed). Happenings spine reads fce_event only. ~200 published historical CTC originals remain in DB as orphaned data. |
| `ctc_person` (+ `ctc_person_group`) | **Re‑owned** | `firstchurch-people` (active displacement at init:5 — same name/slug/meta, zero migration). |
| `ctc_location` | **Retired** | Single record unpublished. All location URLs 301 → `/about/contact-us/`. Footer map coordinates are hardcoded in `ops/scripts/render-osm-map.py`. |

**The CTC deactivation broke no surface.** The Happenings spine, `/engage`, event pages,
ICS feed, `/staff/`, and all legacy redirects were verified 200/301 after deactivation.

### CTC code cleanup (completed)

- `firstchurch-happenings/inc/resolve.php` — ctc_event dispatch removed from `happenings_item_by_id()`
- `mu-plugins/firstchurch-mcp-abilities/shared-writes.php` — ctc_event removed from `set-event-recurrence` type check
- `mu-plugins/firstchurch-mcp-abilities.php` — description updated (no more ctc_event/sermon references)
- `.ddev/commands/host/pull-prod` — stamp counter switched from ctc_event → fce_event
- `maranatha-child/inc/redirects.php` — `template_redirect` priority bumped to 1 (beats `redirect_canonical` now that CTC rewrite rules are gone)

---

## Dependency ledger

| Parent dependency | State | Replacement | Owner |
|---|---|---|---|
| Base templates (`header`/`footer`/`index`/`loop`/`comments`) | **✅ Owned by child** | All extracted as pinned verbatim copies (footer.php earlier; header/index/loop/comments on 2026-06-12). Parent has no `single`/`archive`/`search`/`page` — it routes everything through `index.php` + `loop.php`, so the skeleton is complete. They still call the `ctfw_*`/`maranatha_*` functions in the inventory below — that de‑coupling is the remaining work, not the file ownership. | `maranatha-child` |
| Base stylesheet (`style.css` + `_*.scss`, ~6,500 lines) | **Pending** | The big one: migrate the parent SCSS into the child's Tailwind build so the child stops dequeue/re‑enqueueing the parent's `style.css`. | `maranatha-child` |
| Inherited sub‑partials (`header-top`, `header-bottom`, `loop-header`, `loop-author`, `loop-navigation`, `footer-stickies`) | **Pending** | Pulled by the now‑child header/index/footer via `CTFW_THEME_PARTIAL_DIR`; still resolve to the parent. Port or replace before the parent is removed. | `maranatha-child` |
| Customizer settings, banner, nav, fonts/icons (`ctfw_*`, `maranatha_*`) | **Audited** (see inventory) | Reimplement the live calls below as first‑party helpers; fold customizer reads into the child. | `maranatha-child` |

### Live `ctfw_*` / `maranatha_*` runtime inventory

Audit of every parent‑function / hook / constant the child still calls at runtime, after
the dead‑branch cleanup above. All of these must be replaced before `Template: maranatha`
can be dropped. (`get_template_directory[_uri]()` in `functions.php` is the parent‑stylesheet
dequeue/re‑enqueue and goes away once the child owns its CSS.)

**✅ Decoupled (2026-06-12)** — replaced with first‑party code, no live verification needed
(byte‑identical / faithful replicas, no code hooks the dropped filters):

| Was | Now | How |
|---|---|---|
| `ctfw_make_friendly()` | `fcs_make_friendly()` | `inc/theme-compat.php` replica |
| `ctfw_has_content()` | `fcs_has_content()` | `inc/theme-compat.php` replica |
| `ctfw_has_title()` | `fcs_has_title()` | `inc/theme-compat.php` replica |
| `maranatha_icon_class()` (gallery) | `fcs_icon_class()` | `inc/theme-compat.php` replica (full icon map) |
| `CTFW_THEME_PARTIAL_DIR` | `'partials'` | literalized in header/index/footer |
| `CTFW_THEME_PAGE_TPL_DIR` | `'page-templates'` | literalized in header-banner/map-section/content-footer-short |
| `header`/`index`/`loop`/`comments` skeleton | child‑owned | extracted (above) |

**⏳ Still coupled** — customizer/framework‑bound or render‑critical; needs the running site
to verify before swapping:

| Call | Site(s) | What it does | Replacement effort |
|---|---|---|---|
| `ctfw_customization()` | `footer.php:27,31`, `inc/footer.php:18` | Read customizer (`footer_icon_urls`, `footer_notice`) | low — `get_option()`, but verify keys |
| `ctfw_google_fonts_style_url()` | `inc/font-optimization.php:33` | Google Fonts URL (filtered) | low |
| `maranatha_social_icons()` | `footer.php:26` | Render social icon `<ul>` from customizer URLs | medium — also owns the customizer setting |
| `maranatha_title_paged()` | `partials/header-banner.php:36` | Echo page title + pagination | low |
| `ctfw_current_content_type()`, `ctfw_get_content_template()` | `loop.php:20,42` | Pick the `content-*` template per post type | medium — the loop dispatcher |
| `ctfw_comment`, `maranatha_get_icon_class()`, `maranatha_loop_after_content_used()` | `comments.php` | Comment list callback + nav icons | medium |
| `maranatha_after_content` (action) | `inc/announcements-cta.php:417`, `page-events-upcoming.php:30`, `page-events-calendar.php:36` | Inject content after main | medium — own template restructure |
| `maranatha_content_width` (filter) | `page-events-calendar.php:30` | Force full‑width container | low |
| Inherited sub‑partials (`header-top`, `header-bottom`, `loop-header`, `loop-author`, `loop-navigation`, `footer-stickies`) | header/index/footer | Header chrome, loop headers/nav, footer stickies | medium — port into child |

---

## Child‑theme partial overrides (dead CTC branches) — ✅ cleaned

- `partials/content-footer-short.php` — the `ctc_sermon` / `ctc_location` / `ctc_event`
  branches were **removed** (unreachable: those types are unregistered, so they can never
  appear in a query). This dropped the partial's last calls to `ctfw_sermon_data()`,
  `ctfw_location_data()`, `ctfw_event_data()` and the sermon/location/event icons. The
  `ctc_person`, gallery, and generic branches stay.
- `inc/sermon-structured-data.php` — **deleted** (whole `wp_head` hook gated on
  `is_singular('ctc_sermon')`, which can never fire; it was the last child caller of
  `ctfw_sermon_data()`).
- `partials/content-header-short.php` — **kept as‑is, not dead.** Its `ctc_person`
  thumbnail‑size + title‑link logic is *live*: people are re‑owned in place by
  `firstchurch-people`, so a person can still surface in a generic loop (e.g. search) and
  render through this partial. Its former `ctfw_make_friendly()` / `ctfw_has_content()` /
  `ctfw_has_title()` calls have since been swapped for the first‑party `fcs_*` equivalents
  in `inc/theme-compat.php` (see the decoupled table above).

---

## Next steps

1. ~~**Audit `ctfw_*` / `maranatha_*` runtime calls** — live vs. dead.~~ ✅ Done — dead CTC
   branches removed; live calls catalogued in the inventory table above.
2. ~~**Extract base templates** into the child~~ ✅ Done — `header`/`index`/`loop`/`comments`
   owned as pinned verbatim copies (footer.php was already child‑owned). The parent has no
   `single`/`archive`/`search`/`page` templates, so the skeleton is complete.
3. **De‑couple the `ctfw_*` / `maranatha_*` calls** — *in progress.* ✅ The pure‑logic leaf
   helpers (`fcs_make_friendly`/`has_title`/`has_content`/`icon_class`) and the two path
   constants are done (no live verification needed — see the decoupled table). ⏳ Remaining:
   the customizer/framework‑bound calls and the loop dispatcher in the "Still coupled" table,
   plus porting the inherited sub‑partials. **These touch live rendering and should be
   verified against a running DDEV site before merge.**
4. **Self‑owned stylesheet** — migrate the ~6,500‑line parent SCSS into the child's Tailwind build.
5. **Drop the parent:** remove the `Template: maranatha` line, delete the parent from the tree
   and its `check-deploy-coverage.sh` exemption.
