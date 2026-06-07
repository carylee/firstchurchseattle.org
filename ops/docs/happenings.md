# The Happenings spine — author once, project everywhere

**Status:** Phase 0 (this contract) + Phase 1 (announcement lifecycle) built.
**Date:** 2026-06-07
**Scope:** site-wide content architecture for "things happening at First Church."
**Generalizes:** [`carousel-source-of-truth.md`](./carousel-source-of-truth.md) — the carousel
already merges three sources into one feed; this doc promotes that private trick to the
architecture every surface drinks from.

---

## 1. The principle

> **Author once, project everywhere.** Every "thing happening at First Church" is authored a
> single time as a typed object. A shared resolver projects it onto every surface that needs
> it — lobby screen, website, e-news, bulletin — each with its own curation lens. No surface
> holds its own copy of the content.

The audit (2026-06-07) found the word "announcement" modeled three ways, with each display
surface reinventing the same handful of CTAs independently. The plumbing is good (the MCP
layer, the resolver, reference+override curation, draft-first writes); what was missing is a
single spine. This is that spine.

```
     SOURCES (author once)            SPINE                     SURFACES (project everywhere)
  ┌───────────────────────────┐                           ┌──────────────────────────────────┐
  │ Events      (ctc_event)   │                           │ /live     — "worship, right now" │
  │ Announcements (posts)     │──▶  Happenings   ──▶       │ /carousel — lobby screen loop     │
  │ Evergreen   (carousel_card)│     resolver               │ /engage   — "what's happening"   │
  └───────────────────────────┘  · one typed feed          │ /events   — calendar / list      │
              ▲                   · ref+override curation    │ e-news    — weekly digest        │
              │                   · lifecycle (auto-expire)  │ bulletin  — announcement block   │
       MCP + WP admin                                       └──────────────────────────────────┘
   draft → review → publish
```

---

## 2. The `Happening` contract

Today the resolver (`wp-content/plugins/firstchurch-carousel/inc/resolve.php`) emits an
informal shape — a superset of the slides app's `Announcement`. We promote it to a documented
interface that **every surface consumes**, so no surface re-queries the raw post types:

```
Happening {
  id        string   // "event-7269" | "announcement-7426" | "card-7439"
  source    string   // "event" | "announcement" | "card"
  kind      string   // semantic type; today mirrors `layout` (event/info/qr_callout/…)
  title     string
  blurb     string   // short body / excerpt (the resolver's `body`)
  image     string   // full-size URL, optional
  when      string?  // human date/time, events only ("Sundays at 10:30 am · Sanctuary")
  cta       { url, text }?   // optional call-to-action
  tags      string[]         // future: topic/audience facets (see §6)
  starts    string?  // YYYY-MM-DD, when it begins surfacing (future)
  expires   string?  // YYYY-MM-DD, when it stops surfacing (Phase 1, announcements)
  weight    int      // prominence; 0 = normal, higher floats up (Phase 1, announcements)
}
```

The carousel's existing `layout` / `prompt` / `details` / `backgroundColor` fields remain a
**screen-specific superset** — they ride along for the carousel renderer and are ignored by
text surfaces. See `carousel-source-of-truth.md` §2.

### Source → Happening mapping

Lifted from the resolver's `fccar_*_to_item()` functions — the source of truth for projection:

| Source | Post type | Contributes |
|---|---|---|
| **Event** | `ctc_event` | `title`, `when` (from `_ctc_event_*` date/recurrence/venue), `cta` = registration URL or permalink, `image` |
| **Announcement** | `post` in `announcements` category | `title`, `blurb` (excerpt/trim), `cta` (`fcs_cta_*`), `image`, **`weight`**, **`expires`** |
| **Evergreen** | `carousel_card` CPT | `title`, `blurb`/`prompt`, `cta` (`_fccar_qr_url`), `image`, screen fields (`_fccar_*`) |

---

## 3. Meta key registry

The contract's storage is plain post meta, referenced **by literal string from three places**
(child theme, MCP mu-plugin, resolver). This table is the single source of truth for those keys;
change a key here and in all three call sites together.

| Meta key | On | Type | Written by | Read by |
|---|---|---|---|---|
| `fcs_cta_text` | `post` (announcements) | string | child theme meta box, MCP `*-announcement` | resolver, `[fcs_announcements]` |
| `fcs_cta_url` | `post` (announcements) | string (url) | ″ | ″ |
| **`fcs_weight`** | `post` (announcements) | int (0 default) | child theme meta box, MCP `*-announcement` | resolver (sort) |
| **`fcs_expires`** | `post` (announcements) | string `YYYY-MM-DD` | ″ | resolver (filter) |
| `_fccar_layout` … `_fccar_preservice` | `carousel_card` | mixed | carousel CPT meta box | resolver |
| `_ctc_event_*` | `ctc_event` | mixed | Church Theme Content | resolver |

Registration sites: `fcs_*` in `wp-content/themes/maranatha-child/inc/announcements-cta.php`
(`register_post_meta`, `show_in_rest`); `_fccar_*` in `firstchurch-carousel/inc/cpt.php`.

---

## 4. Lifecycle semantics

Two fields make content **self-maintaining** — the fix for the rot the audit found (stale deck,
5-year-old review queue):

- **`fcs_expires`** — "stop showing this on feed surfaces after date X." An empty value never
  expires. Comparison is `expires >= today` (site-local `Y-m-d`). The post **stays published**
  in the news archive; expiry only drops it from *projected* surfaces.
- **`fcs_weight`** — prominence within a source. 0 = normal (date order preserved); higher floats
  up. "Featured" on `/engage` (Phase 3) means `weight > 0`, auto-sorted — no manual deck.

Events already self-expire (they have dates). Evergreen cards are, by definition, evergreen.

**Boundary — expiry/weight vs. curation:** lifecycle applies to the **auto-assembled** sources
(`fccar_news_items()`, and the Phase-3 `/engage` query). An **explicit deck pin** — a reference
resolved via `fccar_item_by_id()` on the curated `fccar_deck` — is honored regardless: a human
pin is deliberate. This mirrors the resolver's existing "curated-to-empty is honored" rule
(`fccar_resolve()`, around line 36-41).

---

## 5. Surfaces → projections

Each surface is a **filter + curation lens** over the one feed — never its own content store:

| Surface | Filter | Curation | Status |
|---|---|---|---|
| **/carousel** (screen) | pre/post-service | `fccar_deck` (ref+override) | ✅ live |
| **/live** ("here now") | the worship-now set | shared w/ carousel | ❌ hardcoded today (Phase 4) |
| **/engage** ("what's happening") | upcoming + active announcements | weight-sorted Featured, then chrono | ◧ half-built (Phase 3) |
| **/events**, **/news** | chronological | none (pure query) | ✅ live (CTC/core) |
| **e-news** | weekly window | auto-digest + light edit | ⏳ Phase 6 |
| **bulletin** | this Sunday | curated block | ⏳ Phase 6 |

---

## 6. Spine-ownership charter (Phase 2)

Today the resolver lives **inside** `firstchurch-carousel`. Phase 2 lifts `inc/resolve.php`
into a new standalone **`firstchurch-happenings`** plugin that owns the `Happening` contract and
exposes it two ways already wired for the carousel:

- **REST** `GET /wp-json/firstchurch/v1/happenings?surface=…`
- **MCP** `firstchurch/get-happenings`

The carousel keeps its **renderer and deck curation UI** but stops owning the data model; it
becomes one consumer among several. Acceptance test for the extraction: the carousel renders
**identically** before and after. The new plugin needs a `deploy.sh` rsync line and a one-time
`wp plugin activate` on prod (per the new-plugin checklist in `CLAUDE.md`).

`tags[]`, `starts`, and a generalized `weight`/`expires` across all sources are the natural
v2 extensions once the spine is its own home.

---

## 7. Roadmap

| Phase | What | Anchor |
|---|---|---|
| 0 | This contract | — |
| 1 | Announcement `weight` + `expires` (meta, staff UI, MCP, resolver) | self-maintaining feed |
| 2 | Extract `firstchurch-happenings`; carousel consumes it | the spine |
| 3 | `/engage` becomes spine-driven (block); nav link; retire `featured` category | **v1 public hub** |
| 4 | `/live` + carousel share a worship-now set | unify the two renderings |
| 5 | Taxonomy collapse; finish native connection-card; purge review queue | structural cleanup |
| 6 | e-news digest + bulletin announcement block from the spine | new surfaces |
