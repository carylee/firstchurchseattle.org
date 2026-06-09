# The e-news as a Happenings surface — compose on the website, render to email

**Status:** Design (Phase 6 of the Happenings roadmap). Nothing built yet.
**Date:** 2026-06-09
**Scope:** the weekly e-news ("First Church Weekly News") — its content model and authoring
workflow, not its visual design.
**Generalizes / depends on:** [`happenings.md`](./happenings.md) (the spine; this is its
Phase 6) and [`carousel-source-of-truth.md`](./carousel-source-of-truth.md) (evergreen
`carousel_card`s + "curate once, three artifacts draw from it").
**Consumer rail:** Mailchimp keeps the *send* (deliverability, list, translate widget,
unsubscribe, public archive); it stops being where the issue is *authored*.

---

## 1. The problem

Today the weekly e-news is authored **entirely inside Mailchimp**: a staff member duplicates
last week's campaign and hand-edits every block in Mailchimp's block editor. This is the one
content surface at First Church that still holds its **own private copy of everything** — and
it pays for that twice:

- **Re-keying.** Most of the issue's timely content *already exists in the spine.* Open Mic
  Night is authored once as an `fce_event` — so it's already on the lobby carousel, `/events`,
  and `/engage` — and then **re-typed by hand** into Mailchimp. Edit the time in one place and
  the email silently disagrees with every other surface.
- **Manual decay.** "Duplicate last week" carries last week's *stale* items forward; someone
  has to remember to delete the past Open Mic Night, the expired sign-up, the finished drive.
  The spine already solves this (`fcs_expires`, and events self-expire on their date) for every
  *other* surface — but the email never drinks from that tap.

The Happenings principle is **author once, project everywhere.** The e-news is simply the last
surface that doesn't yet project. This doc specifies making it one.

## 2. The newsletter, decomposed

Pulling a representative issue (2026-06-03) apart, every block lands in one of three buckets —
and two of the three are *already spine-shaped*:

### Bucket A — already in the spine, re-keyed by hand today (the waste)
| Newsletter block | Spine source it duplicates | Contract fields |
|---|---|---|
| All-Church Conference (Jun 17, Zoom, register) | **event** | `title`, `when`/`start`, `cta` (registration) |
| Open Mic Night (Thu Jun 11, 6pm, Sanctuary) | **event** | `title`, `when`/`start`, `location` |
| Adult Spirituality / loneliness (Sun 9am, Rm 301) | **event** + sign-up | `title`, `when`, `cta` |
| Graduate recognition (Jun 14) · Pastoral farewell (Jun 21) | **announcement** | `title`, `blurb`, `date`, `cta` |
| Pride parade logistics · drag fundraiser · "Minute for Mother Earth" | **announcement** | `title`, `blurb`, `cta` (often external) |

These should be **projected**, not transcribed. The fix is the same one every other surface
already enjoys: read them from `happenings_resolve()` (events + announcements) over the issue's
week window.

### Bucket B — recurring / evergreen, i.e. `carousel_card` territory
Worship + livestream notice · Shared Breakfast · Centering Prayer (Sun, Rm 302, 10:00–10:15) ·
Caregiver's Support (4th Fri) · Men's Breakfast (2nd/4th Thu, Aurora IHOP) · Nursery (0–5,
9–11:45). These are **evergreen Happenings** — author once, they appear in every issue with no
re-paste, edited only when a fact changes (the IHOP moves). `carousel-source-of-truth.md`
already names the e-news as a downstream consumer of the curated evergreen set.

### Bucket C — genuinely newsletter-only editorial (keep it human)
- **Subject / preview tagline** — *"June is Pride Month! All-Church Conference, Open Mic Night…"*
- **Pastoral Message** — the one block that is real prose (Pastor Kathy's voice).
- **Ordering / what-leads** — editorial judgment about the week.
- **Footer chrome** — comms deadline (Tue noon), giving, social, unsubscribe. Fixed furniture.

> **The payoff in one line:** Buckets A + B are ~70% of the issue and assemble themselves from
> the spine. Bucket C is the ~30% a human should actually be spending the hour on.

## 3. The model: an Issue is a thin curation layer over the spine

An **E-News Issue** is a lightweight WordPress object (a CPT, `enews_issue`, or a page using
issue-scoped blocks). It is **not** a content store — it holds only Bucket C plus *curation
decisions*, and projects Buckets A/B at render time.

```
        SPINE (author once)                 ISSUE (thin, per-week)              RAIL
  events + announcements + evergreen  ──▶   • tagline / subject       ──▶   email render ──▶ Mailchimp
        (happenings_resolve)                • Pastoral Message (prose)        (send + list)
                                            • section order / which to lead
                                            • happenings block(s), week-scoped
                                              (auto-filled, self-expiring)
```

Issue-level fields (Bucket C, the only hand-authored content):

| Field | On `enews_issue` | Notes |
|---|---|---|
| `subject` / `preview_text` | string | the Mailchimp subject + preview tagline |
| post body (block editor) | blocks | the Pastoral Message prose + the assembling blocks below |
| `issue_date` | `YYYY-MM-DD` | the send date; also the window anchor |

The body is composed in **the same Gutenberg block that already powers `/engage`** —
`firstchurch/happenings` (`wp-content/themes/maranatha-child/inc/happenings-block.php`). It
already does server-side assembly with `section` (featured / events / announcements), a count,
look-ahead `weeks` / look-back `days`, an optional `heading`, and `excludeFeatured` de-duping.
An e-news issue is mostly: a prose block, a `section="events" weeks=1` block, a
`section="announcements" days=7` block, and an evergreen block — over a fixed footer.

## 4. The authoring workflow we want

1. **It opens pre-filled, not blank.** A new issue auto-drafts with the week's happenings blocks
   already populated, in date order — because they're projected from the spine, not copied.
   No "duplicate last week."
2. **Stale items are already gone.** Last week's Open Mic Night isn't there: events self-expire
   on their date, announcements drop via `fcs_expires`. The decay problem is solved upstream.
   This is the single biggest ergonomic win and it requires *no new mechanism* — only that the
   e-news reads the same filtered feed every other surface does.
3. **You write the two human parts** — the tagline and the Pastoral Message — and nothing else
   by hand.
4. **You curate, not transcribe** — reorder sections, bump something to lead (`fcs_weight`),
   drop a card. The work is *judgment*, not data-entry.
5. **You preview live** at a `*.ddev.site` / Tailscale URL (a real WordPress render), then send.

## 5. How it reaches the inbox — Mailchimp's role

The website owns **content**; Mailchimp keeps what it is genuinely good at — deliverability, the
subscriber list, the 50-language translate widget, unsubscribe handling, and the public archive
(which already backs `/enews/latest`; see below). Two rails, recommendation first:

- **(Recommended) Render an email-safe HTML version on the site, hand it to Mailchimp.** An
  "email" render template (table-based layout, inlined CSS — a sibling to the existing print
  templates) turns an issue into a campaign body, pushed via the Mailchimp campaign API or
  "import content from URL." Staff stop block-editing in Mailchimp entirely; they hit **Send** on
  a campaign whose body *is* the spine-rendered issue.
- **(Lighter, RSS-native) Drive a templated Mailchimp RSS campaign from a per-surface feed.**
  `GET /wp-json/firstchurch/v1/happenings?surface=enews&weeks=1` already has the `surface`
  param **reserved for exactly this** (`firstchurch-happenings/inc/rest.php`). We already run
  RSS↔Mailchimp plumbing in the other direction — `ops/bin/update-enews-redirect.php` reads the
  `campaign-archive.com` feed every cron tick to keep `/enews/latest` pointed at the newest
  issue. This rail reuses that muscle but is harder to give the Bucket-C editorial polish.

Either way the staff deliverable stops being "a hand-built Mailchimp campaign" and becomes "a
curated WordPress issue"; the email is a *render* of it.

## 6. The honest seams to design around

These are the real blockers, and two are already named on the roadmap:

- **Featured spans posts, not events (roadmap Phase 4).** A dated happening authored as an
  *announcement* carries only its **publish date**, not the event's date — which is why
  `/engage`'s Featured block *suppresses* the date line (`fcs_happenings_block_render`, the
  `$show_meta` hack, documented in `happenings.md` §4). For an email where **"June 17 at 7pm" is
  the entire point**, this hack fights us. **Do Phase 4 first** so dated happenings flow through
  the event source with a real when-line + registration CTA.
- **Ordering is richer than `weight`.** An edited digest needs explicit section order and a
  designated lead, not just `weight`'s coarse float-up. The issue object owns this; the block's
  per-section composition + `excludeFeatured` de-duping is the seed.
- **The Pastoral Message has no spine home.** Smallest lift: a free prose block on the issue.
  Slightly more: model it as an announcement of `kind: message` so a "From the Pastor" card can
  *also* surface on the site. Both are cheap; start with the free block.
- **Look-back vs. send cadence.** The weekly window (`weeks=1` / `days=7`) is a starting default;
  some announcements (a multi-week drive) want to recur across issues until `fcs_expires`. That's
  already the announcement lifecycle — no new field, just author the expiry intentionally.

## 7. Build plan

Incremental, each step shippable and useful on its own:

| Step | What | Unlocks |
|---|---|---|
| 6.0 | **This doc** — lock the model (Issue = thin layer; Mailchimp = rail) | shared target |
| 6.1 | Land roadmap **Phase 4** (Featured spans events; retire date-suppression) | dated happenings render a real when-line in email |
| 6.2 | `surface=enews` projection on `/v1/happenings` (week-window default, e-news lens) | a feed the email can read |
| 6.3 | `enews_issue` CPT (Bucket C fields) + an issue template composing happenings blocks + evergreen + footer | author on the website |
| 6.4 | Email-safe render template (inlined CSS) + preview | a sendable artifact |
| 6.5 | Push-to-Mailchimp (campaign API or import-from-URL) | retire the duplicate-and-edit ritual |

> **Deploy reminder (CLAUDE.md):** an `enews_issue` CPT ships in the child theme or a new plugin;
> if it's a **new plugin**, it does nothing on prod until it's added to `ops/deploy.sh` *and*
> activated (`ssh firstchurch 'cd ~/public_html && wp plugin activate …'`). A green deploy is not
> proof it shipped.

## 8. Open questions for staff

- Is the Pastoral Message ever absent (some weeks just events)? → keep it an optional block.
- Should an issue ever surface *on the website* (a `/news`-style archive of past e-news), or is
  Mailchimp's archive (`/enews/latest`) sufficient? → leans toward "Mailchimp's archive is enough,"
  but the Issue CPT makes a native archive nearly free if wanted.
- How much should `surface=enews` curate vs. show-everything? → start with show-everything in the
  week window (the feed is already self-expiring); add a curation lens only if issues get noisy.
