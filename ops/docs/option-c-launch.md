# Option C — launch readiness, content migration & cutover

Operational companion to [`option-c-design.md`](./option-c-design.md) (the *why/what*). This
is the *how-to-ship*: what's been verified, what's still open, the **content that must be
migrated on prod so the live site matches what the branch shows**, and the cutover steps.

**Branch:** `design/option-c`. **As of 2026-06-13** the branch is **technically
mergeable** — see §1. It is **not yet content-ready on prod** — see §2.

---

## 1. Ship readiness — verification status

### ✅ Done / verified on the branch
- **Unit tests** — `npx vitest run` (in container): 21/21 pass.
- **Lint** — `npx biome check .`: clean (15 files).
- **e2e** — `PLAYWRIGHT_BASE_URL=http://firstchurchseattle.ddev.site:8800 npx playwright
  test` (on **host** — the container lacks libnspr4; the https:8843 cert is untrusted, so
  point at http:8800): 5/5 pass (nav, mobile menu, search, skip-link, worship-live).
- **Connection-card form dark mode** — was a light-only white island in dark mode; the
  "Say hello" CTA links there. Fixed: the plugin's `--fcc-*` tokens now inherit the theme's
  `--fcs-*` (fallbacks preserved for standalone use), bumped to **0.3.2**. Verified the
  card/inputs/text flip; light mode unchanged. (`connection-card.css`, commit on branch.)
- **Palette coverage** — light **and** dark verified on: homepage, /about/, /about/newcomers/,
  /events/, /connection-card/, /worship/live/, /events-calendar/ (grid), /about/staff-2/,
  search results, 404. All chrome + custom-color elements (calendar "today" pill, event
  names, worship action pills) flip correctly.
- **Rebased on latest main** (picks up the merged SVG logo #103 and IA doc #101).

### ⛔ Decisions still open (don't block *merge*, but gate *ship*)
- **Ship Option C at all?** vs. the conservative `design/2026-refresh` branch. Unmerged by choice.
- **Serif or not** — the Fraunces display tokens are dormant; a one-line guide extension would
  bring back the warmer cream/serif look. (`option-c-design.md` §4, §8.)
- **Canvas warmth** — paper-white vs. a hint of cream if interior color reads thin.

### 🔧 Known follow-ups (not blockers; tracked elsewhere)
- Breeze-forms native form already got dark mode in PR #91; connection-card now matches.
- `.maranatha-button` legacy alias still carried by the theme for the breeze-forms plugin;
  drop when that plugin stops emitting it (`theme-independence.md` follow-ups).
- Prod cleanup backlog (delete maranatha themes + CTC plugins, retire dead pages) —
  independent of this design; runbook in `theme-independence.md`.

---

## 2. Content migration — make prod match what the branch shows

⚠️ **This is the gap most likely to be forgotten.** The theme is code (ships on merge), but
the homepage's *appearance* depends on **data that lives on prod**, some of which only exists
in the local DDEV mirror today. Merging the branch ships the theme; it does **not** ship any
of the below. Each is a data op (wp-cli / MCP / wp-admin), to run on prod **after** deploy.

### 2.1 The primary nav — **the big one** (local-only today)
What the design shows is a **four-door menu**: **Visit · Worship · Connect · About**, with
**Give as a utility action** (hardcoded in the header, not a menu item). On prod **right now**
the `header` location points at **"Menu 1"** — the old sprawling structure (What's Happening,
About, Worship, Gather, Serve, Grow + Learn, Fellowship, Children + Youth, Pride + Faith,
Music…). The clean 4-door menu (**"Main 2026"**, term 234) exists **only in the local DB**.

So one of:
- **(a) Build "Main 2026" on prod** (4 top items + their children, all **relative** URLs —
  absolute localhost URLs break the Tailscale dual-host serve, and absolute prod URLs are
  brittle) and assign it: `wp menu location assign <menu> header` (run from `~/public_html`).
- **(b) Coordinate with the IA-2026 execution** (`ia-2026.md`, PR #101) — that project
  produces the *real* restructured nav after office sign-off. If IA ships first, point the
  header at its menu instead of building a throwaway. **Recommended** — don't build two menus.

Either way: the **Give item must be removed** from whatever menu prod uses (the theme renders
Give as a header utility now; a menu "Give" item would double it). The local Main 2026 already
has it removed; Menu 1 still has whatever it has.

### 2.2 `fcs_front_hero` option — the masthead's seasonal notice
The masthead identity copy is **hardcoded in the template**. But the small seasonal-notice
line (`.fcs-mast__notice`) is still pulled from the **`fcs_front_hero`** option: the masthead
heuristic surfaces the first sentence in that option's `content` that contains a **month
name**. Today that's *"Pride Sunday · June 28 · 9:30 am — note the special time!"*.

- **This is a content time-bomb:** after **June 28, 2026** that line is stale but will keep
  showing until the option is edited. **Maintenance step:** update `fcs_front_hero` content
  (drop the Pride sentence post-event) via wp-cli/MCP. Seed/edit script:
  `ops/bin/seed-front-hero.php`; the option is plain editable data.
- Longer-term: an MCP "edit hero" ability is a filed follow-up so the office can change the
  seasonal line without code (`theme-independence.md`).

### 2.3 Mosaic tile targets — exist now, but watch the IA
The five tiles deep-link to `/worship/live/`, `/gather/serve/shared-breakfast/`,
`/gather/music/`, `/gather/pride-at-first-church/`, `/gather/`. **All resolve today (200).**
**But** `ia-2026.md` plans to collapse `/gather/*` into `/engage`. If that IA work ships, the
four `/gather/*` tile URLs in `front-page.php` must be **repointed** (and the creed CTAs
re-checked). Coordinate the two; don't let IA redirects strand the homepage tiles.

### 2.4 Photos — no migration
The five mosaic images ship **with the theme** (`assets/home/tile-*.jpg`), so they deploy with
the code. **But** re-confirm media releases before go-live for any guest-visible Shared
Breakfast or minor images — see `option-c-design.md` §7 and
`~/church/theme-refs/photo-audit/README.md` (26 images flagged `identifiable-children`).

### 2.5 Worship-logistics copy — pick one canonical wording
The same logistics line now exists in three slightly different forms (masthead meta row,
footer, `fcs_front_hero`). Choose one wording and align on ship so they don't drift.

---

## 3. Prod cutover checklist (when "ship Option C" is a yes)

1. **Merge `design/option-c` → main.** CI builds `tailwind.css` on the deploy runner and
   rsyncs per `ops/deploy.sh` (theme **and** the connection-card plugin 0.3.2 are both wired
   in deploy.sh — verified). A green deploy is *not* proof the data ops below ran.
2. **Nav (§2.1)** — build/point the 4-door menu on prod, **relative URLs**, and
   `wp menu location assign … header`. Remove any menu "Give" item.
3. **Hero notice (§2.2)** — edit `fcs_front_hero` to current content; drop the Pride line
   after June 28.
4. **Photo releases (§2.4)** — final check on guest/minor images.
5. **Verify** prod light **and** dark, desktop **and** mobile, against the Tailscale
   reference: homepage fold (mosaic 2nd row peeks), interior banner color, the "Say hello"
   form in dark mode, the SVG logo swap.
6. **Copy alignment (§2.5)** — optional, tidy the three logistics lines.

> Remember the `~/public_html` rule: every remote `wp` command needs `cd ~/public_html &&`
> first, or it fails with "not a WordPress installation."

---

## 4. Branch / PR hygiene (housekeeping, non-blocking)

- **~18 stale local branches**, several marked `gone` (deleted on remote) — safe to prune
  (`git branch -d` / `-D`). The merged PR #103 branch `claude/vigilant-johnson-svzhef` is
  still on the remote and can be deleted.
- **`design/2026-refresh`** — the conservative alternative. Keep as comparison until the
  Option-C-vs-not decision is made, then retire the loser.

---

*Companion docs:* [`option-c-design.md`](./option-c-design.md) ·
[`voice-guide.md`](./voice-guide.md) · [`ia-2026.md`](./ia-2026.md) ·
[`theme-independence.md`](./theme-independence.md) · photo audit
`~/church/theme-refs/photo-audit/README.md`.
