# Phase 5 cleanup — prod runbook (taxonomy collapse + review-queue purge)

**Status:** code piece (finish native connection-card) shipped in the Phase 5 branch. The two
items below are **production-data operations** — they touch WP terms, post status, and plugin
activation, none of which live in git. Run them via `ssh firstchurch` (or the MCP server); they
cannot run from a Claude Code web session. Part of Phase 5 in [`happenings.md`](./happenings.md) §7.

> ⚠️ Every prod `wp` runs from `~/public_html`:
> `ssh firstchurch 'cd ~/public_html && wp …'`. A bare `ssh firstchurch 'wp …'` fails — `~` has
> no WordPress install.

---

## 1. Retire the `featured` category (taxonomy collapse, part 1)

**Why:** the audit found "announcement" modeled three ways and "featured" promotion done by a
legacy `featured` **category**. Phase 4 replaced that with the `fcs_weight` meta (Normal /
Featured / Pinned), which now works across both posts and events and drives the spine's Featured
row. The `featured` category is dead weight.

**Safe to delete — nothing in code reads it.** Every `'featured'` reference in the repo is the
spine's *Featured section* name (weight-driven: `firstchurch-happenings/src/Featured.php`,
`inc/resolve.php`, the `happenings-block`/`enews` `section` attr), **not** a WP term. Deleting the
term changes no code path.

```bash
# 1. Find it and see how many posts still carry it.
ssh firstchurch 'cd ~/public_html && wp term list category --fields=term_id,slug,name,count'

# 2. For any posts still in `featured`, give them real prominence instead, then
#    remove the legacy term assignment. (Adjust the term id from step 1.)
ssh firstchurch 'cd ~/public_html && wp post list --category_name=featured --post_type=post --format=ids'
#    For each post worth promoting: set Featured/Pinned weight (10/20).
#      wp post meta update <id> fcs_weight 10
#    Then drop the featured term from the post:
#      wp post term remove <id> category featured

# 3. Delete the now-empty term.
ssh firstchurch 'cd ~/public_html && wp term delete category <term_id>'
```

**Verify:** `wp term list category` no longer lists `featured`; `/engage` Featured row and the
e-news digest are unchanged (they were never driven by the term).

---

## 2. Finish the `ctc_event` → `fce_event` decommission (taxonomy collapse, part 2)

Collapsing two event post types into one. Per [`events-migration.md`](./events-migration.md) the
live set is already on `fce_event`, the CTC originals are unpublished, and the public pages are
spine-backed — only **step 5 (decommission CTC)** remains.

```bash
# 1. Confirm no upcoming CTC events remain published (should be empty).
ssh firstchurch 'cd ~/public_html && wp post list --post_type=ctc_event --post_status=publish \
  --meta_key=_ctc_event_start_date --meta_compare=">=" --meta_value=$(date +%F) \
  --fields=ID,post_title,post_status'

# 2. Spot-check parity: the spine event list still matches what CTC used to show.
ssh firstchurch 'cd ~/public_html && wp eval "print_r( array_map(fn(\$i)=>\$i[\"title\"], happenings_event_items(8)) );"'

# 3. Deactivate the paid CTC stack. Keep INSTALLED but inactive one cycle as rollback insurance.
ssh firstchurch 'cd ~/public_html && wp plugin deactivate church-content-pro church-theme-content'
```

**Verify after deactivation:** `/engage`, `/upcoming-events/`, `/events-calendar/`,
`/event/<slug>/`, and `/events.ics` all still render the live events (they read the spine, not
`ctc_event`).

**Follow-up code PR (separate branch, only once the above is confirmed in prod):** remove the now-dead
`ctc_event` read-both branches —
- `firstchurch-happenings/inc/sources.php` + `inc/resolve.php` (the `ctc_event` dispatch in
  `happenings_event_items()` / `happenings_item_by_id()`),
- `maranatha-child/inc/event-structured-data.php`,
- the `fcs_weight`/`fcs_expires` registration on `ctc_event` in
  `maranatha-child/inc/announcements-cta.php`.

Gated on step 3 so the spine never loses a source while CTC still holds live data.

---

## 3. Purge the review queue

**What it is:** draft/pending posts awaiting human review in the draft-first workflow — surfaced
by the existing MCP ability `firstchurch/review-queue`
(`wp-content/mu-plugins/firstchurch-mcp-abilities.php`). The audit found it full of years-old
abandoned drafts. **This is a data cleanup, not a code change — the ability stays.**

```bash
# 1. List the queue (events + announcements + sermons, draft + pending), oldest first.
ssh firstchurch 'cd ~/public_html && wp post list \
  --post_type=fce_event,ctc_event,post,ctc_sermon --post_status=draft,pending \
  --fields=ID,post_type,post_title,post_status,post_modified --orderby=modified --order=ASC'
#    (Or call the MCP firstchurch/review-queue ability for the same list with edit URLs.)

# 2. Triage by post_modified. Trash anything clearly stale (>~1yr, never published, no live work).
#    Keep genuine in-flight drafts. Trash first (reversible); --force only once confident.
ssh firstchurch 'cd ~/public_html && wp post delete <id> [<id> …]'        # → trash
ssh firstchurch 'cd ~/public_html && wp post delete <id> --force'          # permanent

# 3. Re-run step 1 — the queue should be down to live work only.
```

**Verify:** the review-queue list returns only current drafts; published content is untouched
(`wp post delete` to trash is reversible from `wp post list --post_status=trash`).
