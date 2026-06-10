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
| `ctc_person` (+ `ctc_person_group`) | **In flight (this doc's workstream)** | `firstchurch-people` (adopts the `ctc_person` type in place — zero data migration) + child display. See below. | `firstchurch-people` |
| `ctc_location` | **To retire** | One record only — convert to a normal Page (address already rendered by `maranatha-child/inc/footer-map.php`). No CPT warranted. | follow‑up |
| Base templates + base stylesheet (`header`/`footer`/`index`/`loop`/`single`/`archive`/`search` + `_*.scss`) | **Pending** | The big one: child must grow its own skeleton + self‑owned CSS before the parent can be dropped. | `maranatha-child` |
| Customizer settings, banner, nav, fonts/icons (`ctfw_*`, `maranatha_*`) | **Pending** | Audit which are still read at runtime; fold the live ones into the child. | `maranatha-child` |

**Cutover gate:** CTC can only be deactivated once *every* `ctc_*` type above is retired or
re‑owned. Until then, first‑party type plugins that **adopt a CTC type name** (people) stay
**dormant** — they register nothing while CTC still does, so there is no double‑registration
and no behaviour change. They flip on automatically the moment CTC stops registering the type.

---

## Workstream: `firstchurch-people`

Replaces CTC's person type with a first‑party plugin, **adopting `ctc_person` in place** —
same post‑type name, same `ctc_person_group` taxonomy, same `_ctc_person_*` meta keys — so
the ~9 existing staff posts, their group terms, headshots, and `/staff/<name>/` URLs keep
working with **zero data migration**. (`ctc_location` is explicitly out of scope — see above.)

### What ships, and when it takes effect

| Piece | File | Live effect on ship | Why |
|---|---|---|---|
| Type + taxonomy registration | `firstchurch-people.php` (`init`, priority 20) | **None until cutover** | Guarded `if ( ! post_type_exists('ctc_person') )`. CTC (priority 10) wins while active; we register only once CTC is gone, then `define( 'FCP_OWNS_PEOPLE', true )`. |
| Data accessor + writer | `inc/person.php` (`fcs_person_data()`, `fcs_write_person()`) | Additive/safe | Reads/writes the same `_ctc_person_*` meta. Decoupled from `ctfw_*`. |
| Pure formatters (TDD) | `src/Person.php` | n/a | Phone→`tel:` link, social‑URL→icon mapping, pronoun/url sanitising. Unit‑tested WP‑free, like events' `src/`. |
| Admin "Person details" metabox | `inc/admin.php` | **None until cutover** | Gated on `fcs_people_active()` so it doesn't double up with CTC's metabox. Staff keep CTC's editor until cutover. |
| MCP `create-person` / `update-person` | `inc/mcp.php` | **Live immediately** | Writes existing `ctc_person` posts via `fcs_write_person()`. Closes the one content gap where agents couldn't help — safe alongside CTC. |
| Child display (single + staff archive) | `maranatha-child/templates/{person-single,staff-archive}.php` + `inc/people-display.php` (swapped via `single_template`/`archive_template`, gated on `fcs_people_active()`) | **None until cutover** | Live `/staff/` stays on the theme's rendering until CTC/parent removal; our templates take over automatically at the flip. **Self-contained** — they bypass `loop.php` and the parent's `content-person-*` partials, so nothing depends on the parent surviving, and the partials need no edits. Styled with the child's existing Tailwind build (no new CSS). |

**One flag governs every behaviour‑changing piece:** `fcs_people_active()` (true once
`FCP_OWNS_PEOPLE` is defined, i.e. we registered the type because CTC no longer does). MCP is
deliberately *not* behind it.

### Ship + cutover checklist

- [x] Plugin scaffolded, registration dormant‑guarded, accessor + writer, `src/Person.php` + tests.
- [x] Admin metabox (gated), MCP abilities (live).
- [x] Child display staged (gated, self‑contained templates that bypass the parent's person partials).
- [x] Wired into `ops/deploy.sh` (mirror w/ `--delete`, exclude dev artifacts) — satisfies `check-deploy-coverage.sh`.
- [x] CI: `firstchurch-people` PHPUnit job + composer‑audit matrix entry.
- [ ] **Deploy** (merge to `main` → CD) and **activate on prod**:
      `ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-people && wp rewrite flush'`.
      (A fresh Claude Code web session has no `ssh firstchurch`; this is a human/CD step.)
- [ ] Add the MCP abilities to the published allowlist in `wp-content/mu-plugins/firstchurch-mcp-abilities.php` if/when people authoring should be exposed to the read‑only MCP client. *(Self‑registered abilities work for the editor client today.)*
- [ ] **At CTC removal (the cutover):** confirm `/staff/` and a profile (`/staff/<name>/`) still
      resolve and render via the child templates; verify the rewrite slug (`staff`) and whether
      `/staff/` is the CPT archive or a Page, and reconcile `has_archive` accordingly.

### Known unknowns to verify at cutover

- **`/staff/` mechanism.** Live singles are at `/staff/<slug>/`. Whether `/staff/` itself is the
  CPT archive or a WordPress Page determines `has_archive` / `rewrite['slug']` on our
  registration. Verify against prod before the flip (the dormant guard makes this safe to defer).
- **Pronouns.** Currently typed into name/position text on the live page. The plugin adds a
  first‑class `_ctc_person_pronouns` field; backfilling existing staff is a content task (MCP).

---

## Next after people

1. **`ctc_location` → Page** (one record; smallest remaining type).
2. **Base templates + self‑owned stylesheet** into the child (the structural blocker).
3. **Customizer/banner/nav/fonts audit** — fold live‑read settings into the child.
4. **Drop CTC + the parent:** deactivate `church-theme-content` + `church-content-pro`, remove
   the `Template: maranatha` line, delete the parent from the tree and its
   `check-deploy-coverage.sh` exemption.
