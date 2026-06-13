# Option C — "The Open Table" — design language & decision record

**Status:** on branch **`design/option-c`** (14 commits on top of `main`, unmerged as of
2026-06-13). Served locally over Tailscale for review
(`https://firstchurchseattle.weasel-barley.ts.net`). Nothing here is on prod yet.

This is the handoff doc for the homepage/visual redesign: what we're building, *why*, the
options we weighed, and the things learned about the church's voice and liturgy that the
design is built on. It assumes the theme is already standalone — see
[`theme-independence.md`](./theme-independence.md) for that — and it is the visual
companion to the **written** [`voice-guide.md`](./voice-guide.md) (corpus-derived prose
rules) and [`homepage-recommendations-2026-06.md`](./homepage-recommendations-2026-06.md)
(the audit that started this).

---

## 1. The problem we're solving

The pre-redesign homepage was a **directory for people who already attend** — a map, a
department grid, eight equally-weighted buttons. But a church homepage's primary reader is
the **person who has never walked in**, deciding in ten seconds whether they'd belong.
Members bookmark the calendar; visitors get the homepage. (Full argument in
`homepage-recommendations-2026-06.md`.)

So the redesign optimizes for one reader and two actions: *plan a visit* and *watch live*.
Everything on the page serves one of those or gets out of the way.

---

## 2. The concept: "The Open Table"

An **editorial** homepage rather than a brochure one. The name comes from the church's own
communion liturgy — *"This is not the table of First Church… it is Christ's Holy Table"* —
and the open table is the organizing metaphor: a place set for whoever shows up.

The page is a vertical sequence, not a grid of equals:

1. **Masthead** — a typographic statement, no hero photo. Opens with the welcome (see §5).
2. **Ticker** — the next few real happenings from the Happenings spine (`fcs_ticker_items()`
   in `front-page.php`), deduped, soonest-first, weekly fixtures filtered out.
3. **Photo mosaic** — five "doors" into the church (Worship / Shared Breakfast / Music /
   Pride + Faith / Community), sized by importance, not uniformly. Photos ship *with the
   theme* in `assets/home/` (deliberate — they're design, not editable content).
4. **Creed band** — the welcome liturgy, finished (see §5 on the refrain structure).

Three concepts were mocked before this was chosen (A "Doorway", B "Bulletin", C "Open
Table"); the user picked C. The mockup HTML is archived in `~/church/theme-refs/mocks/` (scratch lives in
`/tmp/fcs-mocks/` during a session but is wiped on reboot — copy anything worth keeping to
theme-refs) and the rendered comparisons in `~/church/theme-refs/mock-*.jpg`.

---

## 3. How we work on this (the approach)

This redesign was built by **mocking options first, then building the chosen one** — and it
is worth continuing that way, because the user reasons visually and the cost of a throwaway
mock is minutes.

- **Standalone HTML mocks** in `/tmp/fcs-mocks/` (archived to `~/church/theme-refs/mocks/`),
  sharing `shared.css` which pulls the
  *real* theme fonts and palette, so a mock looks like the real site. Serve the dir with
  `python3 -m http.server` and screenshot with the Playwright MCP browser.
- **Present 3–5 labeled variants on one page**, stacked, with the *current* version at top
  for reference, then give a ranked recommendation with the trade-offs named — don't just
  build the first idea. Examples this session: 5 masthead headlines, 4 welcome-band
  treatments, 3 ways to pull the blessing into the masthead (each with measured pixel
  heights so "no added space" was verifiable, not asserted).
- **Build on the branch, screenshot the real site** (light **and** dark, desktop **and**
  ~390px mobile), and **measure** when a claim is dimensional (fold position, section
  height). The 15" MacBook Air fold is ~1470×860 effective; check the mosaic's second row
  peeks above it.
- **One cherry-pickable commit per decision.** The commit subjects read as a changelog of
  the design conversation (`git log origin/main..design/option-c`).

**Build mechanics:** styles are Tailwind v4 source in `assets/src/*.css`, compiled with
`./build-css.sh` **inside the DDEV container** (`ddev exec "cd wp-content/themes/firstchurch
&& ./build-css.sh"`) — the host Node is too old. `assets/tailwind.css` is a gitignored
build artifact; CI builds it on deploy. Font `url()`s in source CSS resolve relative to the
*output* file, so they're `url("fonts/…")`, not `../fonts/`.

---

## 4. The 2022 style guide reconciliation (the load-bearing decision)

Midway through, the user surfaced the official **May-2022 brand style guide**
(`/mnt/backup/firstchurch/dropbox/communication/Style Guide/…jpg`; captured in the
[[brand-style-guide-2022]] memory and §"Photography" below). The question was: *can we do a
web redesign without a brand refresh?* Answer: **yes**, because the guide is a one-page
print-era document that constrains less than expected.

What the guide **specifies**: Raleway (primary), Caflisch Script (secondary); six colors;
the logo lockup; naming ("First Church" / "First Church Seattle" / "First United Methodist
Church of Seattle" — **never "FUMC"**); photo permissions. What it is **silent on**:
background/neutral colors, a type scale, link/button conventions, dark mode. So most of
Option C's *structure* was never off-brand; only its skin was invented.

We revised Option C to the guide ("the style-guide edition", commit `dfb3604`):

- **Palette, sampled from the guide JPG** (truer than CMYK math). These are the live tokens
  in `assets/src/base.css` — note the token *names* still say `maroon`/`gold` for historical
  reasons; they **mean** "primary brand fill" and "small accent":

  | Token | Value | Guide origin |
  |---|---|---|
  | `--fcs-maroon` (primary fill, links) | `#9c3a75` | berry, C45 M93 Y31 |
  | `--fcs-forest` (ticker band) | `#186054` | secondary forest |
  | `--fcs-gold` (kickers as small text) | `#7d6a26` | olive `#9f8838` **darkened for AA** |
  | `--fcs-gold-on-dark` | `#d6c054` | olive lightened for dark fills |
  | `--fcs-emerald-bright` (ticker dates) | `#7be0b4` | emerald `#109d68` lightened |
  | `--fcs-live` (live-now) | `#109d68` | emerald, used as-is |
  | `--fcs-surface` (canvas) | `#fdfcfa` | paper white (guide extension — no neutral defined) |
  | `--fcs-text` (ink) | `#232026` | neutral ink (extension) |

  **Why the olive is darkened:** raw guide olive (`#9f8838`) is only ~3.5:1 on white — fails
  AA for the small kicker text. Lightened/darkened variants for dark mode and contrast are
  *unavoidable* extensions; a print guide doesn't contemplate either, and pure guide colors
  fail WCAG on dark fills.

- **Type:** Raleway carries everything now. Headings are Raleway 600 (capped there — the
  variable font's real top weight, so the browser never fake-bolds); the big display
  moments (masthead, banners, creed) are Raleway **Light 300**. The earlier draft used
  **Fraunces** (a serif) for display — its `@font-face` and the `--font-serif` token are
  **still in the codebase but unused**, so the warm cream/serif look is **one token flip
  away** if the guide is ever extended to bless a display serif. I'd advocate for that
  one-sentence extension — the serif did more for the "open table" warmth than any color —
  but the current branch is strict-guide.

- **Logo:** the real lockup as an **SVG** (`assets/logo-black.svg` / `logo-white.svg`),
  swapped by color scheme via a `<picture>` element in `header.php` (guide rule: black on
  light, white on dark). This replaced an earlier Helvetica live-text approximation and an
  invented "est. 1853" wordmark. The SVGs came from PR #103 (merged to main); the
  `<picture>` wiring is ours.

**Net:** adopting the guide palette ended the "Claude invented the colors" governance
problem at almost no cost to the design, and kept the door open (via the unused serif
tokens) to the warmer earlier direction.

---

## 5. Voice & liturgy — what this design is built on

The prose rules are in [`voice-guide.md`](./voice-guide.md) (seven attributes, signature
phrases, don'ts, all with transcript citations). **Read that first.** What follows is the
*liturgical* discovery this session and how it became structure, not just copy.

### The Community Candle welcome

The user remembered a welcome — *"wherever you are, wherever you find yourself today…"* — and
asked whether it was in the corpus. It is: it's the **Community Candle liturgy**, spoken at
the lighting of the candle, in **~87 of 101 services** (May 2024 → April 2026), then quietly
dropped. Sampling all transcripts plus the longform bulletin archive
(`../youtube/transcripts/`, `../archive/longform/`) surfaced its stable wording and
variants. The canonical adapted-for-web text:

> *Whoever you are, wherever you find yourself today — across the whole spectrum of human
> existence, with your questions and your doubts, in a world that needs repair — may this
> community be a place of **companionship and healing** for you. There is nothing that can
> separate you from God's all-embracing love.*

(Adapted: singular "you," dropped the Sunday-specific clause, kept the church's own words.)

### How it became the page's spine — the refrain structure

The homepage **opens** the welcome in the masthead and **closes** it in the creed band — the
page speaks one sentence, top to bottom:

- **Masthead `<h1>`:** *"Whoever you are, / wherever you find yourself today."*
- **Masthead sub-deck (the blessing, promoted up):** *"May this community be a place of
  companionship and healing for you."*
- **Creed band (the finish):** *"Across the whole spectrum of human existence… there is
  nothing that can separate you from God's all-embracing love."*

The blessing appears in both the masthead and the creed — that is **deliberate**: liturgy
repeats its lines, so it reads as a refrain, not a duplication bug. If it ever needs to be
de-duped, the creed's emphasized phrase can shift (e.g. to "God's all-embracing love").

### Three voice lessons earned the hard way (corrections from the user)

These generalize beyond this design — apply them to any First Church copy:

1. **Don't divide members from visitors.** A "NEW HERE?" kicker was rejected outright: it
   sorts the reader into a category at the door. The welcome must address *everyone* at once
   — which is exactly what the candle liturgy's "whoever you are" does.
2. **Don't over-claim the liturgy as current practice.** The church may be moving away from
   this candle liturgy, so the copy shows the *voice* without asserting "this is what we do
   every week." Adapt and echo; don't quote-and-attribute as a standing ritual.
3. **Light touch; the website is not the worship service.** The user likes the liturgical
   warmth but wants it adapted for a *reader*, not transcribed. Singular address, no
   service-specific scaffolding, no exclamation points (the warmth is in the words).

Also reaffirmed from the voice guide and worth repeating: **"You are welcome to belong,"**
not "You belong here" — belonging is *offered*, never assigned; the assertion form reads as
presumptuous to a newcomer who hasn't chosen it yet.

---

## 6. Page-construction decisions (the changelog, annotated)

Each is one commit on `design/option-c`. Ordered as built.

- **Masthead headline = the candle welcome** (`4ed4fb9`). Chosen from 5 mocked options over
  "All are welcome. / No exceptions." (the prior line), "Come as you are. / Doubts and all.",
  the verbatim mission statement, "Seattle's first church. / Everyone's church.", and "All
  means all." Rationale: it's the one line **only First Church can say** — its own liturgy,
  in the second person, addressing every reader at once. *Side effect to watch:* the explicit
  "all are welcome" phrasing now lives nowhere on the homepage except the Pride tile.

- **Blessing leads the sub-deck** (`b76390a`), chosen from 3 "pull the blessing up" options
  (fold-into-sub-line / right-margin note / lead-with-blessing). The worship logistics
  demote to a one-line meta row. Net masthead growth ~15px — the fold still shows the
  mosaic's second row. Meta copy (`f29d4c1`): *"Worship Sundays · 10:30 am · 180 Denny Way &
  YouTube · doubts welcome · kids welcome · childcare provided · free parking"* — the last
  two answer the practical questions a first-time visitor with kids actually has.

- **Interior banners go brand berry** (`8bf7b6e`). With the canvas now paper-white, interior
  pages had lost all structural color. The page-title banner carries it (white Raleway Light
  title, pale-berry kicker) — the interior-page equivalent of the homepage creed band. This
  was a direct response to the user noting interior pages "look pretty devoid of color." A
  held-in-reserve lever if it's still not enough: tint `--fcs-surface` a step warmer (a
  whisper of the old cream) without leaving the guide.

- **Give moves out of the menu** (`30228fd`). A donate CTA mid-menu read as a navigation
  destination (and its pill sat on the new berry banner). It's now a hardcoded utility
  action in the header's right cluster next to search — visible at **every** viewport, so
  phones get it in the bar rather than behind the hamburger. The `nav_menu_css_class`
  give-tagging filter was removed with it. **Data debt:** the local "Main 2026" menu had its
  Give item deleted; **repeat on prod when this ships.**

- **Footer mirrors the header chrome** (`4a2ad64`). Paper surface + 2px ink top rule instead
  of the berry-dark fill — token-driven, so dark mode flips it. Fixes the creed-band/footer
  "berry on berry" run-together at the foot of the homepage.

- **Vertical rhythm tightened above the fold** (`bdf17c0`). On the 15" Air the mosaic's
  second row sat fully below the fold. Masthead padding and display size came down (clamp cap
  5.6rem → 4.5rem), mosaic rows 11rem → 10rem. Goal state: full first row **plus a peek of
  the second** — the sliver is what tells the eye to scroll.

---

## 7. Photography rules (these have teeth)

From the style guide, and they gate launch:

- Minors **and vulnerable adults** need a media-release on file. The guide explicitly defines
  vulnerable adults to **include "any Shared Breakfast guest."** So a breakfast photo showing
  *guests* (vs. volunteers) needs a release check before public use. The current
  `assets/home/tile-breakfast.jpg` reads as volunteers at the serving line, and the user
  cleared the current tile photos (2026-06-12) — but any swap re-opens the question.
- The kids photos flagged earlier still need a consent check before public use.
- Lists of same-titled people: **women first** (guide rule; applies to the staff directory).

The full photo discovery work — a 2026-06-12 inventory of the comms Dropbox (1,434 images →
628 AI-labeled via 26 fanned-out Haiku agents → 195 hero candidates), the labeling schema,
and the **26 images flagged `identifiable-children`** plus the breakfast-guest consent rule —
is documented in **`~/church/theme-refs/photo-audit/README.md`** (kept out of the repo with
the photos and their consent constraints). Start there before sourcing any new imagery.

---

## 8. Open questions / not yet decided

- **Ship Option C at all?** The branch is unmerged; the user is "especially interested in C"
  and likes the direction, but hasn't said "merge." A parallel `design/2026-refresh` branch
  (conservative evolution) also exists.
- **Serif or not** — the Fraunces display tokens are dormant. One-sentence guide extension
  decides it.
- **Canvas warmth** — paper-white vs. a hint of cream, if interior color still reads thin.
- **`fcs_front_hero` option** still feeds the masthead's seasonal-notice line; the standing
  identity copy now lives in the template. Confirm the seasonal heuristic still makes sense
  under the new masthead.
- **Copy drift** — the worship logistics line now exists in three slightly different forms
  (masthead meta, footer, `fcs_front_hero`). Pick a canonical wording and align on ship.

## 9. When this ships

Ship readiness, the **content that must be migrated on prod** to make the live site match
this branch (the 4-door menu that exists only locally, the `fcs_front_hero` seasonal line,
the `/gather/*` tile targets vs. the IA plan), and the cutover checklist all live in the
operational companion: **[`option-c-launch.md`](./option-c-launch.md)**.

---

*Pointers:* mocks `~/church/theme-refs/mocks/` (scratch `/tmp/fcs-mocks/`) · rendered refs + photo audit `~/church/theme-refs/` ·
voice corpus `../youtube/transcripts/` + `../archive/longform/` · written rules
[`voice-guide.md`](./voice-guide.md) · theme internals
[`theme-independence.md`](./theme-independence.md).
