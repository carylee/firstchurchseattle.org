# Event kinds — rhythms, groups, and one-offs in the Happenings spine

**Status:** Accepted — implemented in the PR that adds this doc (§8 is the commit map).
**Date:** 2026-06-09
**Scope:** the Happening contract, the spine's section lens, and every surface that lists
events (/engage, /upcoming-events, /events calendar, carousel, e-news).
**Builds on:** [`happenings.md`](./happenings.md) (the spine),
[`events-migration.md`](./events-migration.md) (CTC → fce backend).

---

## 1. The problem

The live event inventory (14 published `fce_event` posts as of 2026-06-09) is three
different kinds of thing wearing one shape:

| Kind | Count | Examples | What it really is |
|---|---|---|---|
| **Weekly rhythms** | 4 | Sunday Worship, Shared Breakfast, Centering Prayer, Sunday-evening Bible Study | The standing Sunday pattern — *how the church works*, not news. All four are Sundays, no end date. |
| **Monthly groups** | 3 | Caregiver's Support Group (2nd Thu), Men's Breakfast (2nd & 4th Thu), Book Club (4th Thu) | Ongoing communities you *join*; the cadence ("2nd Thursdays") matters more than the next date. |
| **One-offs** | 7 | Open Mic Night, Church Conference, receptions, Music/Pride/Graduates Sundays | Time-bound, newsworthy — the things that actually need promotion. |

The spine treats them identically: `happenings_event_items()` collapses each event to its
next occurrence and date-sorts. A weekly event's next occurrence is **always ≤ 7 days
away**, so on every date-sorted, count-capped surface the rhythms win:

- **/engage events rail** (3 slots, `happenings_section_items('events', 3, …)`): in any week
  without a nearer one-off, the rail is the same three Sunday staples, forever. The
  one-offs — exactly the items that need the visibility — get pushed out. Same lens, same
  problem in the **e-news** events block.
- **/upcoming-events** (26-week list): the permanent weekly fixtures sit pinned at the top
  every week; new events are buried under them.
- **/events calendar**: every Sunday cell carries 4 identical recurring entries, so special
  Sundays (the signal) don't stand out — and "Sunday Worship" + "Music Sunday" appear as
  colliding 10:30 entries on the same day.
- **carousel auto-deck**: the weekly staples recycle as "events" on the lobby screen —
  telling people already in the building that Sunday Worship exists.

The data already knows the difference (`_fce_recurrence` = `weekly` / `monthly` / `none`);
only the projection layer ignores it. The escape hatch today is manual `fcs_weight`
curation, which treats the symptom per-item.

## 2. The change in one sentence

> Add a derived **`kind`** field (`rhythm` | `group` | `event`) to the Happening contract,
> teach the spine's section lens to filter by it, and let each surface choose which kinds
> it shows — no content re-entry, no new authoring burden.

## 3. The `kind` field

### Values and derivation

`kind` is **derived from recurrence meta the events already carry** — staff author nothing
new. Derivation is a pure class beside `EventWhen` (same CTC-shaped fields array from
`fce_recurrence_fields()` / the CTC meta), unit-tested:

```
FirstChurch\Happenings\Kind::derive(array $fields): string

  weekly  recurrence, no end_date   → 'rhythm'   (a standing pattern)
  monthly recurrence                → 'group'    (an ongoing gathering)
  anything else                     → 'event'    (one-offs; bounded weekly series —
                                                  promotable, not furniture; yearly
                                                  occasions, which are special events,
                                                  not communities you join)
```

An optional **override meta `_fce_kind`** (empty = derived) covers the edge cases the rule
gets wrong — e.g. a weekly drop-in that should read as a group, or a monthly concert
series that should read as events. The override is an MCP/admin-settable string, validated
against the three values.

### Contract & feeds

- `fce_event_to_item()` (`firstchurch-events/inc/source.php:23`) and
  `happenings_event_to_item()` (`firstchurch-happenings/inc/sources.php:112`) add
  `'kind' => …` to the item. `Item::build()` keeps it (non-empty string).
- Announcements and carousel cards don't carry `kind` — it's an event-source field, like
  `when`/`start`.
- `GET /v1/happenings`, `firstchurch/get-happenings` (MCP), and the carousel feed gain the
  key **additively**; every existing consumer ignores unknown keys (the slides contract is
  already a superset model). No versioning needed.

## 4. The lens: sections by kind

`happenings_event_items()` grows an optional kinds filter (null = all, fully
backward-compatible):

```php
function happenings_event_items(int $weeks, ?array $kinds = null): array
```

`happenings_section_items()` (`firstchurch-happenings/inc/resolve.php:48`) changes meaning
for `'events'` and gains two sections:

| Section | Returns | Sort |
|---|---|---|
| `'events'` | kinds `['event']` only — **this is the breaking-by-design change** | next date asc (unchanged) |
| `'rhythms'` *(new)* | kinds `['rhythm']` | **time-of-day** (`start` ISO) — all rhythms share "next Sunday", so date-sort is meaningless; breakfast → centering prayer → worship → evening study |
| `'groups'` *(new)* | kinds `['group']` | next date asc; the card meta line is already cadence-first because `EventWhen::format()` says "Every 2nd Thursday at 9:00 am · Aurora IHOP" |
| `'featured'` | unchanged — explicit `fcs_weight` curation trumps kind; a weighted rhythm *can* be featured on purpose | weight desc, date |
| `'announcements'` | unchanged | |

The `'events'` semantic change is the point: every consumer of that section (/engage block,
e-news block) starts showing one-offs without touching the consumers. It must ship in the
same release as the surfaces that pick up rhythms/groups, so nothing silently disappears
(§6 phasing).

## 5. Surface by surface

| Surface | Change |
|---|---|
| **/engage** (`firstchurch/happenings` block, theme `inc/happenings-block.php`) | `section` attribute gains `rhythms`/`groups` options (editor JS dropdown + block enum). Events rail now shows one-offs. Page composition (editorial, no deploy): add a compact **"Every Sunday" strip** — rendered from `'rhythms'` as a one-line-per-item list (time · title · place), *not* cards — and a **"Groups & gatherings"** card section. |
| **/upcoming-events** (`page-templates/page-events-upcoming.php:31`) | Three groups instead of one flat list: **Coming up** (`'events'`, date-sorted — now the top of the page), then **Every week** (rhythms, time-sorted), then **Monthly groups**. One template, three `happenings_event_items()`/section calls. |
| **/events calendar** (`page-templates/page-events-calendar.php`) | Keep full occurrence expansion — the calendar is the one surface where a weekly event *should* land on every date. But carry `kind` onto the occurrence items and render rhythm occurrences **subdued** (`fcs-cal__item--rhythm`, smaller/muted) so special Sundays read as the signal. v2 option: collapse the four Sunday rhythms into one "Every Sunday" legend row. |
| **Carousel** (`firstchurch-carousel/inc/resolve.php:57,79,93`) | Auto-deck and candidate pool compose `happenings_event_items($weeks, ['event','group'])` — rhythms drop out of the lobby loop (people are in the building; the Sunday pattern can be one evergreen `carousel_card` if staff want it). **Curated decks are untouched** — deck pins resolve by id and can still pin anything. |
| **E-news** (`firstchurch-enews`) | Free, via the shared lens: the events block now renders one-offs. Editorial option: a standing rhythms footer block in the issue template. ⚠ Flag to comms before shipping — the next issue build changes content without anyone editing it. |
| **ICS feed** (`firstchurch-events/inc/feed.php`) | **Unchanged.** Subscribers want everything; the RRULEs are already correct. |
| **Single event page / JSON-LD** | Unchanged. (Future nicety: `kind === 'rhythm'` could emit schema.org `eventSchedule` instead of a bare next `startDate` — out of scope.) |

## 6. Special Sundays (the collision)

Five of the seven one-offs are Sunday-worship variants or attachments (Recognizing
Graduates, Music Sunday, Pride Sunday at 10:30; two receptions "after the worship
service"). Once rhythms leave the date-sorted rails, the collision only remains on the
**calendar grid** ("Sunday Worship" + "Music Sunday", both 10:30, same cell).

- **v0 (zero code, works today):** EXDATE the generic rhythm on special Sundays —
  `fce_skip_dates()` already supports it (`skip_dates` via MCP `update-event`). Add
  2026-06-21 to Sunday Worship's skip dates and the cell shows *Music Sunday* alone, which
  is the truth. The undated "Every Sunday" strip is unaffected. Make this an editorial
  convention in the authoring runbook.
- **v2 (only if v0 chafes):** a real variant relation (`_fce_variant_of` → the rhythm's post
  id) so a special Sunday renders as an annotation *on* the rhythm ("This Sunday: Music
  Sunday"). Not worth building until the convention proves insufficient.

## 7. What this deliberately does NOT do

- **No new CPT, no taxonomy, no content migration.** `kind` is derived; the 14 events are
  already correctly shaped.
- **No change to Featured.** `fcs_weight` stays the explicit cross-kind promotion lever.
- **No change to occurrence math.** RRULE expansion, skip dates, and `EventWhen` are
  untouched; `kind` is a read-side projection.
- **No per-surface reimplementation** — the filter lives in the spine, surfaces pick kinds,
  which is exactly the "one lens" rule the spine exists to enforce.

## 8. Implementation sketch (one PR, ~5 commits)

1. **`Kind` class + tests** — `firstchurch-happenings/src/Kind.php`,
   `tests/KindTest.php` (pure; mirrors `EventWhen`'s style). `fce_kind()` helper in
   `firstchurch-events/inc/event.php` (fields + `_fce_kind` override).
2. **Sources carry `kind`** — `fce_event_to_item()`, `happenings_event_to_item()` (CTC kept
   for the transition; same fields shape), occurrence items included.
3. **Lens** — `happenings_event_items($weeks, $kinds)`; `happenings_section_items()` adds
   `rhythms`/`groups`, filters `events` to one-offs; REST/MCP docs note the new key.
4. **Theme surfaces** — block section options (PHP enum + `assets/happenings-block.js`),
   rhythm-strip renderer, upcoming-page grouping, calendar `--rhythm` styling
   (`assets/mobile.css`), child-theme version bump.
5. **Carousel** — auto-deck/candidate-pool kind filter.

Then content/editorial (MCP, no deploy): place the new blocks on /engage, choose the
e-news layout with comms, EXDATE the known special Sundays, and fix two data nits found in
the audit — Sunday Worship's venue is the string "Worship" (should be the Sanctuary), and
Centering Prayer lost "Room 302" in migration (it was in CTC's free-text time field; set it
as the venue).

## 9. Risks

- **`'events'` narrows everywhere at once.** Shipping the lens change without the new
  surface sections would make rhythms vanish from the site. Mitigation: one release, and
  the /engage + upcoming-page block placements land the same day (the blocks render
  nothing for an unknown section, so order of operations is: deploy code → place blocks).
- **E-news content shifts silently** (§5). Tell comms; ideally land before a Thursday build.
- **Derivation surprises** (bounded weekly series, yearly events). The `_fce_kind` override
  is the relief valve; `firstchurch-get-intake`-style MCP exposure means an agent can fix a
  misclassified event in one call.
