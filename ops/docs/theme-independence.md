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
| Base templates + base stylesheet (`header`/`footer`/`index`/`loop`/`single`/`archive`/`search` + `_*.scss`) | **Pending** | The big one: child must grow its own skeleton + self‑owned CSS before the parent can be dropped. | `maranatha-child` |
| Customizer settings, banner, nav, fonts/icons (`ctfw_*`, `maranatha_*`) | **Pending** | Audit which are still read at runtime; fold the live ones into the child. | `maranatha-child` |

---

## Child‑theme partial overrides (dead CTC branches)

The following parent‑theme loop partials still contain dead code branches for CTC post
types. They are harmless (none of these post types can appear in queries now that CTC is
deactivated), but should be cleaned up eventually:

- `partials/content-header-short.php` — `ctc_person` thumbnail size + title link logic
- `partials/content-footer-short.php` — `ctc_sermon`, `ctc_event`, `ctc_person`, `ctc_location` buttons

---

## Next steps

1. **Audit `ctfw_*` / `maranatha_*` runtime calls** — determine what's still live vs. dead.
2. **Extract base templates** into the child (header/footer/index/loop/single/archive/search).
3. **Self‑owned stylesheet** — migrate the ~6,500‑line parent SCSS into the child's Tailwind build.
4. **Drop the parent:** remove the `Template: maranatha` line, delete the parent from the tree
   and its `check-deploy-coverage.sh` exemption.
