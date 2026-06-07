# The website as the source of truth for the announcement carousel

**Status:** Phases 1–4 built (CPT + resolver + feed + curation screen) + a live web
renderer (the `/carousel/` kiosk page — see §12) + a Curate workbench UX pass (see §13).
**Date:** 2026-06-06 (workbench pass 2026-06-07)
**Scope:** WordPress (`firstchurchseattle.org`) — content model, curation UI, and a feed.
**Consumer:** the slides app in `../hocuspocus/apps/slides` (render only; not changed by this).
**Implementation:** `wp-content/plugins/firstchurch-carousel/` (the `carousel_card` CPT,
the resolver, `GET /wp-json/firstchurch/v1/carousel`, the `firstchurch/get-carousel` MCP
ability, a `wp firstchurch-carousel seed` evergreen seeder, and the **Carousel → Curate**
admin screen with its `POST /wp-json/firstchurch/v1/carousel/deck` save endpoint).

---

## 1. Goal

The pre-/post-worship **announcement carousel** (a looping deck of cards shown before and
after the service) should be **created and curated on the website**, not hand-assembled in
PowerPoint or re-typed into a YAML file. Two of its three content classes already live in
WordPress; the third has no home yet; and nothing today lets a staff member *curate* (pick,
order, decorate) the deck. This doc specifies the experience that closes that gap.

The same curated list also feeds the **print bulletin** ("upcoming events") and the **e-news**,
so we curate once and three artifacts draw from it.

### Decisions locked in (2026-06-06)
- **Curation lives fully in WordPress.** The website owns the ordered deck; the slides app
  only fetches the resolved feed and renders.
- **Evergreen cards get a dedicated `carousel_card` custom post type.** They are staff-editable
  without a code deploy.
- This doc is the spec to iterate on before any code.

---

## 2. The contract (the seam between the two systems)

The slides pipeline does **not** want slides from us. It wants one flat, ordered list —
`Announcement[]` from `@church/service-model` — and turns each entry into a carousel card,
**choosing the card layout by the shape of the data** (`apps/slides/app/src/service/compose.ts
→ announcementCards()`):

| Announcement shape | Card layout chosen |
|---|---|
| has `when` | `event` |
| `title` + `body` | `info` |
| `ctaUrl` + (`body` or `title`) | `qr_callout` |
| `title`, no `body` | `divider` |
| `body` only | `info` (fallback) |

The `Announcement` record (the *entire* contract we must emit):

```ts
{
  id: string,
  title?: string,
  body?: string,
  when?: string,            // human string: "Sundays at 7:00 p.m. · On Zoom"
  ctaUrl?: string,          // becomes the card QR target
  ctaText?: string,
  image?: string,           // background photo URL (slides app crops + darkens it)
  qr?: string,              // explicit QR target (ctaUrl wins if both present)
  qrCaption?: string,
  preserviceOnly?: boolean, // dropped from the post-service loop
}
```

**What the slides app owns (we do NOT do these):** QR-code generation, UTM tagging
(`utm_source=slides&utm_medium=slide_qr&utm_campaign=service_<date>`), 16:9 cropping +
gradient-darkening of backgrounds, font/layout/auto-fit, and the looping-GIF bake. We supply
**plain data + a background image URL + a link**; the renderer does the rest.

> Implication: the website's job is narrow and well-defined — produce a correct, ordered
> `Announcement[]`. Everything visual stays downstream. The richer six-layout `card:` model
> (intro/divider/qr_callout/event/info/feature) is an *internal* representation of the slides
> app; we only ever need to hit the shape-detection above. (If we later want to drive layout
> explicitly rather than by shape, that's a v2 extension to the feed — see §10.)

---

## 3. The three content classes and where each lives

| Class | Example cards today | WordPress home | Status |
|---|---|---|---|
| **Events** | Bible Study, Book Club, Caregiver's Support Group | `ctc_event` (Church Theme Content): `_ctc_event_start_date`, `_ctc_event_venue`, recurrence, featured image | **exists** + MCP read/write |
| **News** | one-off announcements with a CTA / link | Announcements-category posts: already carry `cta_text`, `cta_url`, featured image, excerpt | **exists** + MCP read/write |
| **Evergreen** | intro/mission, "Worshipping with Us!" divider, bulletin/e-news/connection-card QR callouts, nametags, hearing devices, children's activity bags, Sunday school, "Communion at Home" (preservice-only) | — | **new: `carousel_card` CPT (§4)** |

The first two are **authored for their own sake** (they have a public life on
`/upcoming-events`, `/engage`, the post body). The carousel *references* them — it must never
duplicate their content. The third class is **carousel-native**: it exists only to be shown in
the deck.

---

## 4. New custom post type: `carousel_card`

A lightweight CPT for evergreen / standing cards. Each post is one card. Fields:

| Field | Type | Notes |
|---|---|---|
| `layout` | enum: `intro` `divider` `qr_callout` `info` `feature` | which of the slide layouts this renders as |
| `title` | text | maps to `Announcement.title` (also used as `divider` heading) |
| `body` | text/textarea | maps to `Announcement.body`; `info` cards support `- ` bullets |
| `prompt` | text | for `qr_callout` (maps to `body`) |
| `details` | text | for `feature` (italic line) |
| `qr_url` | URL | maps to `ctaUrl` |
| `background` | media attachment | maps to `image` (featured image is the natural slot) |
| `background_color` | color | solid-color fallback when no photo (qr_callout/divider/feature) |
| `preservice_only` | bool | maps to `preserviceOnly` |
| `active` | bool (status) | inactive cards stay in the library but aren't offered for decks |

**Why a CPT and not a config file:** staff can reword the hearing-devices card or swap the
e-news QR target without a git commit + deploy. The set is small (~12 cards) and stable, but
*editable by the people who run worship*, which is the whole manifesto.

Editing UX: a normal WP edit screen is fine for these — a layout dropdown that shows/hides the
relevant fields (e.g. `prompt` only for `qr_callout`). This mirrors the slide editor's
data-driven `CardFields` form (`apps/slides/app/src/ui/BlockForm.tsx`), so the field contracts
match what the renderer expects.

---

## 5. The curation screen ("Carousel")

One admin page under its own top-level menu, role-gated to the same editors who manage
events/announcements. It is a **playlist editor over live content**, built around the
"90%-automated + human tweak" philosophy the slides pipeline already follows.

### 5.1 Auto-assembled default deck
On load, the screen proposes a deck by pulling live content:
- **Evergreen** cards (all `active` `carousel_card` posts) in their configured order.
- **Upcoming events** — `ctc_event` with a `start_date` in the next *N* weeks (configurable,
  default ~8), soonest first, recurrence expanded to the next occurrence.
- **Recent news** — published Announcements-category posts from the last *M* days
  (configurable) that carry something projectable (a CTA, or a short body).

This means the deck **mostly maintains itself**: as events pass and new ones publish, the
default shifts. Staff prune and arrange rather than build from scratch.

### 5.2 What the curator does (drag + toggles, never pixels)
- **Include / exclude** any candidate.
- **Reorder** by drag (the carousel plays in this order; group dividers before their section,
  e.g. an "Upcoming Events" divider ahead of the events run).
- **Preservice-only** toggle per card (drops it from the post-service loop — e.g. "Communion
  at Home").
- **Background** override (pick/replace the image; events/news default to their featured
  image).
- **Short title / when override** — optional per-card overlay when the public title is too
  long for a slide ("Bible Study: Visions of Jesus in the Gospels" is fine on a slide, but a
  long news headline may need trimming). Stored as an override, **the source post is never
  edited**.

### 5.3 Storage model: references + overrides
The deck is **not** a copy of content. It is an ordered list of entries:

```
{ source: "event" | "announcement" | "card", ref: <post_id>, overrides: { background?, title?, when?, preserviceOnly? }, position: <int> }
```

Resolving the deck = walk the list, load each source post, apply overrides, project to an
`Announcement`. Editing the underlying event/post updates the card automatically; the deck only
stores *curation intent*.

Persisted as a single option / CPT (`carousel_deck`) so there is exactly one "current deck."
(If you ever want dated/named decks — e.g. a special Christmas Eve carousel — the same model
supports multiple deck posts keyed by date; out of scope for v1.)

---

## 6. The feed

Resolved, ordered `Announcement[]` exposed two ways (same resolver behind both):

1. **REST:** `GET /wp-json/firstchurch/v1/carousel`
   - `?variant=preservice|postservice` (postservice drops `preserviceOnly`; default returns
     all with the flag intact and lets the consumer filter, matching `selectCards()`).
   - Public-readable (it's projecting already-public content); no secrets.
2. **MCP ability:** `firstchurch/get-carousel` on the existing MCP server
   (`wp-content/mu-plugins/firstchurch-mcp-abilities.php`), so the slide Worker / an AI agent
   can fetch it the same way it reads events and announcements today. This is the path
   `CONSOLIDATION-PLAN.md §8` anticipated ("the Worker can fetch announcements from the website
   MCP server").

The slides app's `composeDeck()` already accepts `svc.announcements`; the integration on that
side is "fetch the feed → drop it into the Service → render," with **no slide-side code change
required** beyond the fetch.

---

## 7. Mapping rules (WordPress → `Announcement`)

| Source | → `Announcement` fields |
|---|---|
| `ctc_event` | `title` ← post title (or override); `when` ← formatted `start_date` (+ recurrence summary, + venue if useful); `ctaUrl` ← event permalink or registration link; `image` ← featured image; → lands as an **event** card |
| Announcement post | `title` ← post title (or override); `body` ← excerpt; `ctaUrl`/`ctaText` ← existing `cta_url`/`cta_text` meta; `image` ← featured image; → lands as **info** or **qr_callout** by shape |
| `carousel_card` | direct field-to-field (see §4); `prompt`→`body` for qr_callout; → lands as its configured `layout` (shape-detection happens to agree because the fields are chosen to match) |

`when` formatting is the one bit of real logic: turn `start_date` + recurrence meta into the
human string the `event` card prints ("Every 4th Friday · 4:00 pm on Zoom"). The recurrence
data is already in `_ctc_event_recurrence*` meta (the MCP plugin reads/writes it).

---

## 8. Preservice vs. postservice

The slides app plays the **same pool** twice and filters: preservice shows everything;
postservice drops `preserviceOnly` cards (`renderCard.ts → selectCards()`). So the website only
needs to *set the flag*; it never produces two lists. The "Communion at Home — prepare a solid
and liquid" card is the canonical preservice-only example.

---

## 9. Why this also wins for the bulletin and e-news

The print bulletin consumes the **same** `Announcement[]` (the bulletin projection groups the
`when`-bearing ones into its "upcoming events" table; the rest render as announcement blocks).
A correct carousel feed therefore *is* the bulletin's upcoming-events source, and the e-news's,
too. One curation act → carousel + bulletin + e-news. That is the central behavior change the
manifesto in `../hocuspocus/CLAUDE.md` calls for, delivered through one screen.

---

## 10. Open questions / v2 extensions

1. **Layout override vs. shape-detection.** v1 leans on the slides app's shape-detection
   (§2). If curators want to *force* a layout (e.g. make a news item a `feature` with a cover),
   we'd add an explicit `layout` field to the feed and a matching path in the slides app. Worth
   it only if shape-detection proves too blunt.
2. **`feature` cards** (left cover image + details) don't map cleanly from events/news — they
   suit book/curriculum promos. Probably a `carousel_card`-only layout for now.
3. **Recurrence → `when` string** quality. How smart should the formatter be? Start simple
   (next occurrence + a recurrence phrase), refine against real events.
4. **Ordering ergonomics.** Auto-group events under an auto-inserted "Upcoming Events" divider?
   Or leave all ordering manual? Lean auto-insert with manual override.
5. **Multiple/dated decks** (special services). Model supports it (§5.3); deferred.
6. **Caching / freshness.** The feed reads live content; a short transient cache is fine, but
   the deck should reflect a just-published event without a manual rebuild.

---

## 11. Suggested build phases

1. ✅ **`carousel_card` CPT** + its edit screen (the only new *content*). Seed it with the ~12
   evergreen cards currently in `apps/slides/content/announcements/announcements.yaml` via
   `wp firstchurch-carousel seed`.
2. ✅ **Resolver + REST feed** (`/wp-json/firstchurch/v1/carousel`) over the three sources with
   the §7 mapping — testable immediately against the slides app by pointing it at the feed.
3. ✅ **MCP ability** `get-carousel` wrapping the same resolver.
4. ✅ **Curation screen** (§5) — **Carousel → Curate**: drag to reorder, add/remove
   candidates, preservice-only toggle, and title/when/background overrides; stored as
   ordered references + overrides (`fccar_deck` option) and saved via
   `POST /wp-json/firstchurch/v1/carousel/deck`. The feed resolves through the saved deck
   when present (looking each reference up live, applying overrides, skipping deleted
   content) and falls back to the auto-assembled default (evergreen by menu_order → events
   by date → news by date) otherwise. "Reset to default" forgets the deck.
5. ⬜ **Bulletin / e-news** wired to the same feed (mostly downstream work in `../hocuspocus`).
6. ⬜ **Slides-side faithful `intro`/`feature`** — teach the slides ingestion to honor the
   feed's explicit `layout` (today those two degrade through shape-detection; see §10.1).

Phase 2 is the smallest end-to-end vertical that makes real cards flow; everything else
layers on top.

---

## 12. The live web renderer (`/carousel/`)

**Decision (2026-06-06):** render the carousel **as a live web page served by WordPress**,
not by reproducing the slides app's GIF/PPTX bake in PHP.

The realization that makes this cheap: the slides app's "renderer" is already a *browser* —
each card is an HTML/CSS string Chromium lays out; everything downstream (`fontkit` auto-fit,
the SVG-`foreignObject`→canvas trick, `gifenc`, `pptxgenjs`) exists only because the playback
medium is a **PowerPoint file**, so the live HTML has to be frozen into a looping GIF. Point a
browser at a URL instead and all of that machinery is unnecessary — none of which has a good
PHP equivalent on shared hosting anyway.

So `wp-content/plugins/firstchurch-carousel/inc/render.php` adds a `/carousel/` route that:
- resolves the feed **in-process** (`fccar_resolve()`, no HTTP round-trip),
- emits a bare, full-screen document (no theme chrome) with the items inlined as JSON,
- and lets `assets/carousel.js` render the six layouts, generate QR codes client-side
  (vendored MIT `assets/vendor/qrcode-generator.js`, UTM-tagged), scale a fixed 1280×720
  stage to the viewport, crossfade through the deck, and silently re-pull the feed every
  5 min so a newly published event appears without touching the screen.

`?variant=preservice|postservice` selects the loop; `?seconds=N` tunes dwell time. The look is
a **close-enough** echo of the slides cards (gold `#D4A256` accent, white Raleway, darkened
photo backgrounds) — deliberately *not* a pixel-faithful port of the baked font-size ladders,
since the browser does live layout.

**Playback:** point a smart-TV browser / kiosk box / fullscreen tab at
`https://firstchurchseattle.org/carousel/?variant=preservice`. This is a *separate display
moment* from the worship PowerPoint, shown before/after service.

**Still deferred (intentionally):** the GIF/PPTX path stays in `apps/slides` for now — the
announcement slides remain embedded in the worship deck the AV operator runs. The live page is
an additional surface, not yet a replacement. Wiring the slides bake to source from this feed
(or retiring it in favor of the live page) is future work; offline/own-a-file fallback for the
booth is an open question (the live page needs network at the projector).

---

## 13. The Curate workbench (UX pass, 2026-06-07)

The pieces of §5 worked but felt disjoint: you could do *most* things from Curate but had to
leave for the rest, and the menu didn't even land you there. This pass makes **Curate the
complete front door**, on one principle: *every action a curator needs happens on this screen
or in a panel that slides over it — never a full-page navigation that loses unsaved work.*

- **Curate is the front door.** The top-level **Carousel** menu now opens Curate (the weekly
  job); the standing-card library is demoted to a **Standing Cards** submenu beneath it (the
  CPT registers `show_in_menu => false`, with `parent_file`/`submenu_file` filters keeping the
  menu highlighted while editing a card).
- **Edit in place via an adaptive drawer.** Clicking ✎ on a tile (or **＋ New standing card**)
  opens a slide-over editor that adapts to the source: a **standing card** gets a full content
  editor (layout/title/body/prompt/details/QR/background/preservice) that saves the card itself
  over `POST /wp-json/firstchurch/v1/carousel/card` and folds the result back into the deck —
  no navigation; an **event/announcement** gets the override editor (title/when/background/
  preservice) plus a deep link to edit the original, making explicit that the public post is
  never touched. Card validation runs through one `fccar_sanitize_card_input()` shared by the
  REST endpoint and the classic metabox so the two authoring paths can't drift.
- **Unsaved-work protection.** A dirty indicator, a `beforeunload` guard, and a debounced
  `localStorage` draft that offers to restore an unsaved prior session. Deliberately **no
  autosave to the feed** — "Save deck" stays the explicit *publish to the live carousel* so a
  half-built deck never reaches the kiosk.
- **Preview the show inline.** A **Preview deck** lightbox plays the *current, unsaved*
  arrangement (the kiosk's fade-through-black loop, fed from the in-memory deck) with a
  preservice/postservice toggle and play/pause + prev/next — judge flow and timing before
  publishing.
- **An organized shelf.** The Available pool gains a search box, **All/Cards/Events/News**
  filter chips, a live count, an empty state, and a start-date label on event tiles.
- **A readiness strip.** A summary (card count · preservice-only · play-postservice · warnings,
  reading "✓ looks ready" when clean) plus per-tile ⚠ markers for cards with no title, a photo
  layout with no background, or an event whose date has already passed.

**Tests.** The plugin now carries a standalone PHPUnit suite (mirroring `firstchurch-breeze-
forms`): a bootstrap shims the handful of WP primitives the pure logic touches, and the suite
covers deck-entry + card-input sanitization, layout shape-detection, the recurrence → "when"
formatter, and the date helpers (`fccar_short_date`/`fccar_is_past_date`). Wired into CI as a
`carousel PHPUnit` job; `vendor/`, `tests/`, and the Composer/PHPUnit config are excluded from
the prod deploy (`ops/deploy.sh`).
