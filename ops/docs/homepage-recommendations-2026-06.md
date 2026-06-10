# Homepage Recommendations — June 2026

*Prepared from a full-site Playwright audit (desktop + mobile) of firstchurchseattle.org,
June 10, 2026. Companion to the audit fixes merged in PR #61. Most items below are
content edits (wp-admin / MCP); theme-side items are flagged for the child theme.*

---

## Executive summary

The homepage is well-built and honestly photographed, but it is organized as a
**directory for people who already attend** — map, departments, eight
equally-weighted buttons. A church homepage's primary audience is the **person
who has never walked in**, deciding in under ten seconds whether they'd belong.
Members bookmark Engage and the calendar; visitors get the homepage.

The information architecture underneath is strong (the Engage page is
excellent). This is a homepage problem, not a site problem — and almost all of
it is editable content, not engineering.

**Two KPIs to manage the homepage by:** clicks on *Plan Your Visit* and clicks
on *Watch Live*. Everything on the page should serve one of those two actions
or get out of their way.

---

## The 10-second test

What a first-time visitor currently sees: a tagline, a worship time,
"(masks optional)", a paragraph asking them to read the newsletter ("click the
button below" — ambiguous among four identical buttons), then a full-screen
Google Map.

What they cannot find without hunting:

- **Who you are.** "A refuge of inclusive Christianity" — the site's best
  sentence — is buried on the About page. In Seattle, people actively search
  for an LGBTQ-affirming, justice-oriented church. The homepage whispers this
  at zero volume.
- **What a service is like.** No photos up close, no video, no "what to
  expect."
- **What to do next.** There is no *Plan Your Visit* path on the front door,
  despite a good newcomers page existing at `/about/newcomers/`.

---

## Hero: recommendations + draft copy

### Problems

1. "(masks optional)" reads as "this site is unmaintained" in 2026.
2. The newsletter pitch is member-retention comms occupying acquisition real
   estate, and it sends people off-site to a Mailchimp archive.
3. Four equal ghost buttons serve four different audiences (seeker, seeker,
   member, donor) — no decision is made for the visitor.
4. Thin white text over the plum-washed photo is contrast-borderline.

### Draft copy (ready to paste)

**Recommended — keeps the established tagline:**

> # Serving the Heart of the City
>
> A progressive, inclusive United Methodist community in downtown Seattle —
> all are welcome, no exceptions.
>
> **Worship Sundays · 10:30 am · 180 Denny Way · in person & live on YouTube**
>
> [ **Plan Your Visit** ]  [ Watch Live ]

- *Plan Your Visit* — filled/primary button → `/about/newcomers/`
- *Watch Live* — outlined/secondary button → `/worship/live/`
- Delete the newsletter paragraph from the hero entirely (the footer's E-news
  Sign-up is its correct home).
- Drop the ABOUT FIRST CHURCH / LATEST NEWS / SUPPORT SHARED BREAKFAST buttons
  from the hero. About lives in the nav; news moves below the fold; Shared
  Breakfast gets a real homepage section (below).

**Alternate — invitation-led, if you're open to retiring the tagline:**

> # All are welcome. No exceptions.
>
> First Church is a progressive United Methodist community that has served
> the heart of Seattle since 1853.
>
> **Sundays · 10:30 am · 180 Denny Way · in person & live on YouTube**
>
> [ **Plan Your Visit** ]  [ Watch Live ]

**Seasonal variant — June (Pride), as a banner above or replacing the subhead:**

> **Pride Sunday · June 28 · 9:30 am** (note the special time!) — worship with
> us, then march together in the Seattle Pride Parade.
> [ Pride at First Church → ]

Voice notes for whoever edits: warm, plain, second person. No insider
vocabulary ("Engage," "connectional," committee names). Every button label is
a verb phrase a stranger would understand.

---

## Page structure: give the page a heartbeat

Nothing on the homepage changes week to week, so returning visitors have no
reason to return and the page reads as static brochure. Three changes, in
order of impact:

### 1. Add a "This Sunday" block *(theme work + light weekly content)*

Date, sermon/series title, special music, children's programming note, one
button. The church already produces this data weekly (bulletin, e-news), and
the MCP content pipeline (`mcp-editor`) already posts weekly announcements —
First Church is unusually well-positioned to keep this block fresh with
near-zero human effort. The child theme's `happenings-block` module is a
natural starting point.

### 2. Demote the map *(theme work)*

A first-timer needs the map *after* deciding to visit. Replace the full-width
live embed with a compact visit card: address, time, "free parking in our
garage" (a genuine Seattle selling point), and a Directions button over a
static map image that click-throughs to Google Maps. Side benefit: the live
Maps JS + tiles are a large share of the homepage's 6.6 MB / 71 requests.

### 3. Lead with the story, not the org chart *(content)*

"Together, we can feed 15,000 hungry people every year" is the most compelling
sentence on the site, currently on an interior page. One homepage section —
that headline, one photo, one quote from a breakfast guest, one **Support
Shared Breakfast** button — says more about who this church is than all three
department billboards combined.

Also: adopt a **seasonal takeover rhythm** (Advent, Holy Week, Pride). Pride
Sunday June 28 — with a time change and a guest preacher — should be on the
homepage *now*.

---

## Technical items

| Item | Status / action |
|---|---|
| Meta description | **Missing.** Title tag is "Homepage - First Church Seattle". Set both in Yoast: title e.g. "First Church Seattle — Inclusive United Methodist Church, Downtown Seattle"; description with service time + identity line. Five minutes, real SERP impact. |
| Page weight | 6.6 MB / 71 requests. Compress/resize the hero image (responsive `srcset`), static-load the map. Target ≤ 2 MB. |
| Hero text contrast | Add a solid scrim band behind hero text *(theme work)*. Fixes readability and WCAG contrast in one move. |
| Schema | Yoast Organization markup present; upgrade to `Church` type with service times. |
| Headings | Clean (one H1, sensible H2s) — no action. |
| Nav label | Rename **Engage → What's Happening** (the footer already uses this label; visitors don't "engage", they look for what's happening). |

---

## Roadmap

**This week (content edits, no code):**
delete "(masks optional)" · paste the new hero copy + buttons · move the
newsletter pitch out of the hero · add the Pride Sunday banner · set homepage
title/meta description in Yoast.

**Next (child-theme work in this repo):**
"This Sunday" homepage section · compact visit card replacing the map embed ·
hero image optimization + text scrim · optional latest-service video embed.

**Ongoing (strategy):**
manage the homepage by the two KPIs (Plan-a-Visit clicks, Watch-Live clicks) ·
monthly featured story (Shared Breakfast guest, new member) · seasonal
takeovers so the page always reflects what the church is feeling this month.

---

*The gap is entirely between "a site that describes a church" and "a site that
invites you to one."*
