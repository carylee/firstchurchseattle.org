# Theme independence — retiring the parent + Church Theme Content

**Goal:** reach a point where `firstchurchseattle.org` runs on `maranatha-child` alone,
with the vendored `maranatha` parent theme and the third‑party **Church Theme Content**
plugins (`church-theme-content` 2.7 + `church-content-pro` 1.3) removed. This is the
endgame the `feat/event-kinds`, sermon‑retirement, and now the people work are all walking
toward — not a big‑bang rewrite, but function‑by‑function extraction into first‑party code.

This doc is the **map**: what still depends on CTC / the parent, who owns each piece, and
the order to retire them. Update it as each dependency falls.

---

## The two third parties, and the split they own

It's worth being precise, because "the theme" gets blamed for things the **plugin** owns:

| Concern | Owned by | Notes |
|---|---|---|
| Content **types** — `ctc_sermon`, `ctc_event`, `ctc_person`, `ctc_location` (+ their taxonomies, meta, admin meta boxes) | **Church Theme Content** plugin (`church-theme-content` + `church-content-pro`) | The data layer. Gitignored (third‑party), active on prod. |
| Content **display** — `single`/`archive` templates, `content-{sermon,event,person,location}-*` partials, the ~6,500‑line stylesheet, and the `ctfw_*` template helpers (`ctfw_person_data()`, `ctfw_format_phone()`, `maranatha_social_icons()`, …) | **`maranatha`** parent theme (bundles Church Theme Framework under `framework/`) | The presentation layer. Vendored in git for drift detection — **never edited** (`CLAUDE.md`). |

So retiring a content type is **two** jobs: replace CTC's *registration* (a first‑party
plugin) **and** replace the theme's *display* (child‑theme templates + CSS + accessors).
`firstchurch-events` is the worked example: the `fce_event` plugin owns the data, and
`maranatha-child/single-fce_event.php` owns the display.

---

## Dependency ledger

| CTC / parent dependency | State | Replacement | Owner |
|---|---|---|---|
| `ctc_sermon` (+ 5 taxonomies) | **Retired** | Sermon archive 301s to `/worship/live/` (`maranatha-child/inc/redirects.php`); YouTube history is the surface. No first‑party CPT needed. | — |
| `ctc_event` | **Migrating** | `firstchurch-events` (`fce_event`, RRULE‑backed) + `single-fce_event.php`. Spine reads both during transition (`ops/docs/events-migration.md`). | `firstchurch-events` |
| `ctc_person` (+ `ctc_person_group`) | **Re-owned** | `firstchurch-people` (adopts `ctc_person` in place — active displacement at init:5, zero data migration, no dependency on full CTC decommission) | `firstchurch-people` |
| `ctc_location` | **To retire** | One record only — convert to a normal Page (address already rendered by `maranatha-child/inc/footer-map.php`). No CPT warranted. | follow‑up |
| Base templates + base stylesheet (`header`/`footer`/`index`/`loop`/`single`/`archive`/`search` + `_*.scss`) | **Pending** | The big one: child must grow its own skeleton + self‑owned CSS before the parent can be dropped. | `maranatha-child` |
| Customizer settings, banner, nav, fonts/icons (`ctfw_*`, `maranatha_*`) | **Pending** | Audit which are still read at runtime; fold the live ones into the child. | `maranatha-child` |

**Cutover gate:** CTC can only be deactivated once *every* `ctc_*` type above is retired or
re‑owned. Until then, first‑party type plugins that **adopt a CTC type name** (people) stay
**dormant** — they register nothing while CTC still does, so there is no double‑registration
and no behaviour change. They flip on automatically the moment CTC stops registering the type.

---

## Workstream: `firstchurch-people` ✅ Complete

Replaced CTC's person type with a first‑party plugin, **adopting `ctc_person` in place** —
same post‑type name, same `ctc_person_group` taxonomy, same `_ctc_person_*` meta keys — so
the ~9 existing staff posts, their group terms, headshots, and `/staff/<name>/` URLs keep
working with **zero data migration**.

The plugin uses **active displacement**: at init:5 it unhooks `ctc_register_post_type_person`
and removes `ctc-people` theme support, then registers the type itself at init:20. This means
the cutover is independent of full CTC decommission — ctc_event (rollback insurance),
ctc_sermon (published-but-redirected), and ctc_location (pending) remain registered by CTC.

### What ships, and when it takes effect

| Piece | File | Live effect on ship | Why |
|---|---|---|---|
| Type + taxonomy registration | `firstchurch-people.php` (`init`, priority 20) | **Immediate** | Displaces CTC at init:5; registers at init:20 when `post_type_exists('ctc_person')` is false. `define( 'FCP_OWNS_PEOPLE', true )`. |
| Data accessor + writer | `inc/person.php` (`fcs_person_data()`, `fcs_write_person()`) | Additive/safe | Reads/writes the same `_ctc_person_*` meta. Decoupled from `ctfw_*`. |
| Pure formatters (TDD) | `src/Person.php` | n/a | Phone→`tel:` link, social‑URL→icon mapping, pronoun/url sanitising. Unit‑tested WP‑free. |
| Admin "Person details" metabox | `inc/admin.php` | **Immediate** | Gated on `fcs_people_active()` — activates when plugin owns the type. |
| MCP `create-person` / `update-person` | `inc/mcp.php` | **Always live** | Writes existing `ctc_person` posts via `fcs_write_person()`. |
| Child display (single + staff archive) | `maranatha-child/templates/{person-single,staff-archive}.php` + `inc/people-display.php` | **Immediate** | Gated on `fcs_people_active()`; swapped via `single_template`/`archive_template`. **Self-contained** — bypass `loop.php` and the parent's `content-person-*` partials. Tailwind-styled. |

**One flag governs every behaviour‑changing piece:** `fcs_people_active()` (true once
`FCP_OWNS_PEOPLE` is defined, i.e. we registered the type because CTC no longer does). MCP is
deliberately *not* behind it.

### Ship + cutover checklist

- [x] Plugin scaffolded, active displacement, accessor + writer, `src/Person.php` + tests.
- [x] Admin metabox (gated), MCP abilities (live).
- [x] Child display staged (gated, self‑contained templates that bypass the parent's person partials).
- [x] Wired into `ops/deploy.sh` (mirror w/ `--delete`, exclude dev artifacts).
- [x] CI: `firstchurch-people` PHPUnit job + composer‑audit matrix entry.
- [x] **Menu:** old page link (ID 2127 → `/about/staff-2/`) replaced with custom link `/staff/`.
- [x] **Redirect:** 301 `/about/staff-2/` → `/staff/`.
- [x] **Old Staff Page (ID 65):** unpublished (draft) — CPT archive at `/staff/` takes over.
- [x] **Deploy + activate on prod:** code deployed via CD; plugin activated; rewrites flushed.

### Verified at cutover

- **`/staff/` mechanism.** `/staff/` is the CPT archive (rewrite slug `staff`). The old Staff
  Page (ID 65, slug `staff-2`, child of About at `/about/staff-2/`) used `page-templates/people.php`
  and was linked from the menu. Resolved by unpublishing the page, repointing the menu item to
  `/staff/`, and adding a 301 redirect.
- **Pronouns.** The `_ctc_person_pronouns` field is available for backfilling via MCP. Existing
  staff had pronouns inline in the post title (e.g. "Rev. Elizabeth Ingram Schindler [she/her]").

---

## Next after people

1. **`ctc_location` → Page** (one record; smallest remaining type).
2. **Base templates + self‑owned stylesheet** into the child (the structural blocker).
3. **Customizer/banner/nav/fonts audit** — fold live‑read settings into the child.
4. **Drop CTC + the parent:** deactivate `church-theme-content` + `church-content-pro`, remove
   the `Template: maranatha` line, delete the parent from the tree and its
   `check-deploy-coverage.sh` exemption.
