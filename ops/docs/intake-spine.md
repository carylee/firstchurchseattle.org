# Intake → Spine — one funnel for capturing events, news & announcements

**Status:** Design / proposed. Supersedes the undocumented `fc_intake` queue inside
`firstchurch-breeze-forms` (Breeze-only capture; see §8).
**Date:** 2026-06-12.
**Scope:** the *front of the funnel* — how unstructured inflow (email, Breeze forms,
verbal mentions, attachments) becomes a voice-correct, structured draft in the spine.
Not the spine itself, and not the authoring abilities — those exist.
**Depends on / reuses:** [`happenings.md`](./happenings.md) (the spine), the
`firstchurch-mcp-abilities` create-* surface (`create-event`, `create-announcement`,
media upload, carousel cards), [`voice-guide.md`](./voice-guide.md), the
`firstchurch-stock-photos` picker, and [`enews-spine.md`](./enews-spine.md) (the last
projection surface). See [`event-kinds.md`](./event-kinds.md) for the kind model.

---

## 1. The problem

Events, news, and announcements arrive at First Church through a mix of channels, none
of them structured:

- **Email to `comms@firstchurchseattle.org` — the dominant channel.** A forwarded blurb,
  a parishioner's three loose sentences, a **Word document with the description and image
  *inside it***, a flyer PDF, a phone photo of a paper flyer.
- **Breeze forms** — semi-structured, but only the event-request forms, and only what the
  submitter chose to type.
- **Verbal / "a pastor heard about it"** — mentioned to staff in a hallway, never written
  down until someone remembers to.

Three recurring pains across all of them:

1. **Poor, unstructured descriptions.** No date, ambiguous location ("the parking lot"),
   no time, "members must RSVP" phrasing that doesn't match how we actually speak.
2. **Assets are trapped.** The image and copy are often *inside* a `.docx` or PDF, not
   attached as usable files.
3. **Re-keying and inconsistency.** Whoever processes it re-types it into the site, in
   whatever voice they're in that day, and the same event submitted twice (email *and*
   Breeze) gets entered twice or dropped.

We already solved **authoring** — the spine is the canonical model, the MCP surface drafts
into it, the voice is checked in, and every other surface (website, calendar, carousel,
e-news) projects from it. What's missing is the **front door**: a single place the messy
inflow lands and is turned into clean spine drafts.

## 2. The core reframe — AI at the *front* of the funnel, not the back

Today's `fc_intake` is a **passive parking lot**: it stores raw Breeze rows and waits for a
human (or agent) to come draft from them later. That is backwards. Staff *attention* is the
scarce resource; messy inflow is abundant. So flip it:

> **Every channel drops into one capture bus. An AI does the understanding — extraction,
> structuring, asset-handling, voice-standardization, gap-detection — *at capture time*,
> before a human looks. What lands in front of a human is not a raw row but a finished,
> voice-correct spine draft with its provenance and a confidence read. The human's job
> collapses from "transcribe + write + format" to "approve, nudge, or chase."**

If we build this, "intake as a queue of raw rows" genuinely goes away. There is no separate
triage CPT to babysit. There is: messy stuff in → polished drafts out → a person says yes.

## 3. The pipeline

```
   CAPTURE            UNDERSTAND               REVIEW              SPINE
  ┌────────┐        ┌──────────────┐        ┌──────────┐       ┌──────────┐
  │ email  │──┐     │ extract      │        │ approve  │       │ fce_event│
  │ breeze │──┼──▶  │ parse attach │  ──▶   │ fix+pub  │  ──▶  │ announce │ ──▶ every
  │ verbal │──┤     │ voice rewrite│        │ needs-   │       │ carousel │     surface
  │ chat   │──┘     │ images       │        │  info ───┼──┐    └──────────┘     projects
  └────────┘  Item  │ gaps + score │        └──────────┘  │          ▲
                    │ dedup/merge  │              ▲        │          │
                    └──────────────┘              │        │     provenance
                                                  └────────┘     stays on entity
                                            clarify the submitter
```

## 4. Stage 1 — Capture: one front door, every shape

The unifier is the **Item** — a single source-agnostic capture record (the instinct already
in `fc_intake`'s `_fc_intake_source`, kept and generalized). Email is the *bus* everything
can ride; anything not already email is trivially turned into an Item.

| Channel | How it becomes an Item | Notes |
|---|---|---|
| **Email to comms@** (hero) | Mailbox ingestion: body + **all attachments** → one Item | The unlock — this channel has never been captured before |
| **Breeze forms** | Existing reader; folds into the same Item shape | Already built (§8) |
| **Verbal / staff** | (a) magic address / BCC comms; (b) **conversational** — staff open Claude (they have MCP) and say "add a thing: youth car wash next Sat 10–2…" | No form to learn |
| **Public submit form** (optional) | Structured POST → Item | For people who *want* to give clean data; we no longer *depend* on it |

**Email-in mechanics (decision in §9):** behind Cloudflare, the clean push path is
**Cloudflare Email Routing → Worker → POST to a WP REST intake endpoint** (attachments in
the payload, no polling). The simpler start that matches our existing cron-poll habit is an
**IMAP poll** of the comms mailbox from a WP-Cron worker. Start with whichever lands a real
email as an Item fastest; graduate to the Worker.

## 5. Stage 2 — Understand: the AI does the work humans hate

For each new Item, an agent run produces a **proposed draft**, not a parsed row:

1. **Read everything, including attachments.** Pull text *out of* `.docx`/PDF, and pull
   **embedded images out of those docs** into the WP media library. Kills the "copy + image
   trapped inside a Word doc" pain directly.
2. **Extract to the spine contract.** Map to `create-event` / `create-announcement` inputs:
   `title`, `description`, `start_date`, `time`/`time_text`, `venue`, `registration_url`
   (CTA), `recurrence`, `kind`, `image_url`/`image_id`. Resolve relative dates
   ("next Tuesday") against the received date.
3. **Rewrite in our voice — as a gate, not a nicety.** `voice-guide.md` goes in as the
   system instruction: welcome before information, **invitation never command**, plain and
   warm. "Members must RSVP by Friday" → "You're invited — let us know by Friday if you'd
   like to join."
4. **Handle imagery.** Supplied image → hero. None → propose one from `firstchurch-stock-photos`
   (already has MCP search/import). No more imageless cards.
5. **Detect gaps + score confidence.** "No date found", "location ambiguous", "two plausible
   times." This decides the Item's fate in Stage 3.
6. **De-dupe / merge.** The same event often arrives via email *and* Breeze *and* a hallway
   mention. Cluster into one Item with several sources; staff see it once.

### The extraction contract

The agent emits one JSON object per Item, aligned to the existing create-* abilities so
Stage 3 is a thin call, not a translation:

```jsonc
{
  "target": "event",                 // event | announcement (router picks the create-* ability)
  "draft": {                          // shape = create-event / create-announcement input
    "title": "Youth Group Car Wash",
    "description": "<voice-corrected blurb>",
    "start_date": "2026-06-20",
    "time": "10:00",
    "time_text": "10am–2pm",
    "venue": "Church parking lot",
    "registration_url": "",
    "kind": "",                       // usually derived from recurrence
    "image_url": "<extracted-or-stock>"
  },
  "voice_applied": true,
  "confidence": 0.86,
  "gaps": [                           // empty = high-confidence, auto-draftable
    { "field": "venue", "question": "Is this the main lot or the 8th Ave lot?" }
  ],
  "sources": [ { "channel": "email", "ref": "<message-id>", "received": "2026-06-10T14:03" } ],
  "raw_excerpt": "<original text, kept verbatim for provenance>"
}
```

## 6. Stage 3 — Human-in-the-loop: approval, not authoring

**Opinionated call: don't build a separate raw-triage UI.** The agent creates a **real spine
draft** (`status: draft`, which the create-* abilities already default to) carrying
**provenance meta** back to its source(s) and a confidence flag. The "queue" is then just
*drafts pending review* in the editor staff already know — augmented with a small
**provenance panel** ("Source: email from Jane, 6/10 — original below; AI extracted these
fields; 2 things it wasn't sure about"). Three buttons cover the real cases:

- **Approve & publish** — the high-confidence majority; one click into the spine.
- **Fix & publish** — correct the one wrong field, then publish.
- **Needs info → chase the submitter** — Stage 4.

This is what makes it *streamlined*: a clean event goes from a parishioner's email to live
on the site with one human glance and one click. Only genuinely ambiguous Items sit in a
holding state — and even those already have a draft started.

## 7. Stage 4 — Close the loop back to the source (the quality flywheel)

Descriptions are poor because nobody asks the follow-up. So the agent **drafts the
follow-up**: *"Thanks for sending the Tuesday potluck! Two quick things so we can post it:
what time does it start, and is childcare provided?"* A human approves the send (or it
auto-sends for known-safe gaps like a missing time). The reply returns to the **same Item**,
the draft updates, confidence rises. Over time submitters learn the shape and inflow quality
climbs on its own.

## 8. Stage 5 — The spine makes it consistently available, everywhere

Once approved it's a normal spine entity, so it *automatically* appears across every surface
already built: website Happenings, the calendar, the carousel (if featured), and the **e-news**
(itself a Happenings surface, `enews-spine.md`). "Captured once, enriched once, available
consistently" falls out of the architecture we already have. Provenance meta stays on the
entity, so months later we can answer "where did this come from?"

## 9. What this reuses, adds, and retires

**Reused as-is:** the spine model; every `create-*` MCP ability and its draft-first default;
media upload; `firstchurch-stock-photos`; `voice-guide.md`; the e-news surface; and the
source-agnostic Item instinct already in `fc_intake`.

**New (the actual build):**
1. **Email ingestion** — the single biggest gap.
2. **Attachment understanding** — `.docx`/PDF → text + extracted images.
3. **The processing agent** — turns Items into voice-correct drafts (the §5 contract).
4. **Provenance + confidence** layer on drafts (meta + a small editor panel).
5. **The clarification loop** (§7).

**Shrinks or retires:** the raw-row triage queue. `fc_intake` stops being a workspace and
becomes, at most, a thin **provenance/source log** for Items too ambiguous to even draft.
The current Breeze-only intake (`firstchurch-breeze-forms/inc/intake-*.php`,
abilities `list-intake`/`get-intake`/`set-intake-status`) is the **v0** of this — it proved
the Item shape and the cron-poll discipline. It folds in as one capture channel among several.

### Architecture forks (decide early)

- **Email in:** Cloudflare Email Routing → Worker → WP REST (push, attachments forwarded)
  **vs.** IMAP poll from WP-Cron (simpler, matches existing pattern). *Recommend: start IMAP
  poll, graduate to the Worker.*
- **Who runs the AI:** a **scheduled headless agent** (Claude Agent SDK / Claude Code run)
  that already has the MCP toolset — it can read the mailbox, parse docs, search stock photos,
  and call `create-event` with reasoning — **vs.** a hand-rolled single API call inside PHP.
  *Recommend: the agent; it reuses everything and degrades gracefully.* A "comms triage agent"
  that runs hourly (or on new mail) and leaves drafts behind.

## 10. Roadmap — a pragmatic first slice

So it's real in a week, not a quarter. Each step is independently shippable.

1. **Email → Item.** Stand up comms-mailbox ingestion; land each email (body + attachments)
   as a capture Item. Captures the dominant channel for the first time.
2. **Agent → draft.** One agent run that, for a new Item, extracts + voice-rewrites + creates
   a **draft event/announcement** with provenance meta and a confidence note. Text-only first.
3. **Review in place.** Provenance panel on the draft + the three buttons. No clarification
   loop yet.
4. **Then layer in:** attachment image extraction (`.docx`/PDF), the submitter clarification
   loop, dedup/merge, and folding Breeze + conversational capture into the same bus.

## 11. Risks & open questions

- **PII.** Items and provenance carry submitter contact details — keep the `fc_intake`
  discipline (`public => false`, every meta `show_in_rest => false`, abilities gated to
  `edit_posts`). The clarification loop *sends* email, so guard it behind human approval by
  default.
- **Spam / bad actors via the email front door.** A public `comms@` is open. Treat extraction
  output as untrusted; never auto-publish (drafts only); rate-limit; keep the human gate.
- **Voice over-correction.** The rewrite must preserve facts (dates, names, prices) exactly
  while changing register. Keep `raw_excerpt` verbatim so a reviewer can diff against it.
- **Dedup false-merges.** Merging two *different* events is worse than showing two. Start
  conservative (suggest, don't auto-merge) until the signal is trusted.
- **Cost / cadence.** Hourly agent runs over every inbound email have an API cost; batch per
  run and only process *new* Items.
