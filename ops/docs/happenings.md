# The Happenings spine — author once, project everywhere

**Status:** Phases 0–2 built — the contract, announcement lifecycle, and the extracted
`firstchurch-happenings` spine (resolver + `GET /v1/happenings` + `firstchurch/get-happenings`).
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
  blurb     string   // short body / excerpt (the resolver's `body`) — feed/card surfaces
  content   string?  // full raw body, rendered by the surface. Carried ONLY by the
                     //   by-id detail projection (happenings_item_by_id), e.g. the event
                     //   single page — feed items stay lean. Don't populate in feed readers.
  image     string   // full-size URL, optional
  url       string   // canonical permalink (the title links here)
  when      string?  // human date/time, events only ("Sundays at 10:30 am · Sanctuary")
  start     string?  // ISO 8601 start datetime, events only — the machine value
                     //   behind `when` (date-only if no clock time is set). For
                     //   schema.org Event startDate.
  location  string?  // venue, events only — the machine value behind `when`'s
                     //   trailing "· Sanctuary". For schema.org Event location.
  date      string?  // YYYY-MM-DD publish date, news only
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
| **Event** | `ctc_event` / `fce_event` | `title`, `when` (from date/recurrence/venue meta), `start`+`location` (machine values behind `when`; `fce_event` via `fce_event_to_item`), `cta` = registration URL or permalink, `image` |
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

**Featured spans events (2026-06-09, Phase 4a).** `happenings_featured()` promotes weighted
*posts AND upcoming events* — either source can be set to Featured/Pinned (`fcs_weight`), and
the pure `Featured::rank()` orders the merged pool by weight then date. A dated happening is now
authored as a real **event**, so it flows through the event source with a structured when-line
("June 17 at 7:00 pm") and a registration CTA. The `/engage` Featured date-suppression is
accordingly **scoped to announcements only**: a featured event shows its genuine when-line, while
a weighted announcement's misleading publish date stays hidden (see `fcs_happenings_block_render`).
Staff set event prominence in the **Promotion** box on the event editor (event types carry
`fcs_weight`; expiry stays posts-only since events self-expire by date).

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
| **/live** ("here now") | the worship-now set | shared w/ carousel | ❌ hardcoded today (Phase 4b) |
| **/engage** ("what's happening") | upcoming + active announcements | weight-sorted Featured, then chrono | ◧ half-built (Phase 3) |
| **/events — list** (`/upcoming-events/`) | upcoming, look-ahead window | none (`happenings_event_items`) | ✅ spine-backed (child template) |
| **/events — calendar** (`/events-calendar/`) | month grid | none (`happenings_event_occurrences`) | ✅ spine-backed (child template) |
| **/event/&lt;slug&gt;** (single) | one event | none (`happenings_item_by_id`) | ✅ spine-backed (child template) |
| **/news** | chronological | none (pure query) | ✅ live (core) |

> **/events is spine-backed (2026-06-08).** Both pages were parent-theme templates
> querying `ctc_event` directly; they went empty when the live set migrated to
> `fce_event`. They now project the spine via `maranatha-child` page templates
> (`page-templates/page-events-{upcoming,calendar}.php`) reusing the `/engage`
> `.fcs-card` language. The calendar needs concrete per-day dates, so the spine
> grew `happenings_event_occurrences($from,$to)` — the occurrence-expanded
> counterpart to `happenings_event_items()` (which collapses each event to its
> next date). fce events are fully RRULE-expanded; CTC events sit on their start
> date only (legacy, being decommissioned).
>
> Event links resolve to a real **single page** at `/event/<slug>/` — itself a spine
> surface: `single-fce_event.php` renders the projected Happening from
> `happenings_item_by_id('event-<id>')` (now dispatched to both `ctc_event` and
> `fce_event`), reading only the freeform body straight from the post (the contract
> is a lean summary). So the destination a projection points at is also a projection.
| **e-news** | weekly window | auto-digest + light edit | ⏳ Phase 6 |
| **bulletin** | this Sunday | curated block | ⏳ Phase 6 |

---

## 6. Spine ownership (Phase 2 — done)

The resolver now lives in the standalone **`firstchurch-happenings`** plugin, which owns the
`Happening` contract and exposes it two ways:

- **REST** `GET /wp-json/firstchurch/v1/happenings?weeks=…&days=…` (`surface` reserved)
- **MCP** `firstchurch/get-happenings`

The spine feed is **events + announcements** — the website-facing Happenings. Evergreen
`carousel_card`s stay with `firstchurch-carousel`, which now **consumes** the spine
(`happenings_event_items()`/`happenings_news_items()`/`happenings_item_by_id()` + the
`happenings_item()/text()` helpers) and composes its cards on top, keeping its renderer + deck
curation UI. The extraction was behavior-preserving: the `/v1/carousel` feed is byte-identical
before and after. The pure projection logic (`Id`, `Item`, `Layout`, `Text`, `EventWhen`) is
unit-tested under `firstchurch-happenings/tests/`.

> `firstchurch-carousel` now **requires `firstchurch-happenings` active** — after the first
> deploy run `ssh firstchurch 'wp plugin activate firstchurch-happenings'`.

`tags[]`, `starts`, and a source-registry filter for additional sources are the natural
extensions now that the spine is its own home.

---

## 7. Roadmap

| Phase | What | Anchor |
|---|---|---|
| 0 | ✅ This contract | — |
| 1 | ✅ Announcement `weight` + `expires` (meta, staff UI, MCP, resolver) | self-maintaining feed |
| 2 | ✅ Extract `firstchurch-happenings`; carousel consumes it | the spine |
| 3 | ✅ **`/engage` spine-driven** — block, event kinds (rhythm/group/one-off), lens filters by kind, Featured row driven by `Featured::rank()`, deduplication, styled CTAs; "featured" category retired (#17, #19–#21, event-kinds series) | **v1 public hub** |
| 4a | ✅ **Featured spans events** — weighted events join the Featured row with a real when-line + CTA; date-suppression scoped to announcements; staff Prominence control on event editors | dated happenings render true |
| 4b | `/live` + carousel share a worship-now set (the `/live` renderer is the slides app, a separate repo — deferred) | unify the two renderings |
| 5 | ✅ **Taxonomy collapse, connection-card cutover, review-queue purge** (#36, #56, #58). CTC not decommissioned — sermons were retired via 301 redirects instead (#61, #62, #94), so both CTC plugins remain active. See [`phase-5-cleanup.md`](./phase-5-cleanup.md). | structural cleanup |
| 6 | ✅ **E-news** — CPT, spine-driven composition, email render, Mailchimp push, branded template adopted (addendum A/B/C). **Bulletin** ⏳ not started. See [`enews-spine.md`](./enews-spine.md). | new surfaces |
