# Website improvements — phased plan

A prioritized, multi-phase plan for modernizing `firstchurchseattle.org`. It
separates **code-side** work (ships through the normal PR → merge → CD flow)
from **prod-config** work (wp-cli / dashboards on the live HostGator box — a
fresh Claude Code web session can't SSH there, so those phases are checklists
for a human).

Each code phase is a self-contained, cherry-pickable PR (per the commit
discipline in `CLAUDE.md`).

Status legend: ☐ not started · ◐ in progress · ☑ done.

---

## Phase 0 — Baseline & verification (no changes) ☐
**Config-only.** Confirm the findings below against live prod before acting, and
capture a "before" snapshot to measure against.

- Verify on prod: `cd ~/public_html && wp plugin list --status=active`. Confirm
  whether `utm-event-tracker-and-analytics` is actually broken (folder missing),
  whether Akismet has an API key, whether Google Site Kit is connected.
- Capture a Lighthouse / PageSpeed baseline (performance, a11y, SEO,
  best-practices) for the home page + one sermon page + the events page.
- Confirm Cloudflare is fronting the site and who controls its dashboard — this
  decides where HSTS and edge caching live.

## Phase 1 — Prod hygiene (config-only, no code) ☐
Fastest risk reduction, zero deploy. Do each step one at a time and eyeball the
site after (all reversible).

- Deactivate broken `utm-event-tracker-and-analytics`; clean its stale
  transients / option residue.
- Deactivate genuinely-unused plugins (Site Kit if it stays disconnected; the
  stale `wp-maintenance-mode` splash). Decide Akismet: add a key or remove.
- Fix the Yoast Twitter handle `@firstumcseattle` → `@firstchurchseattle`
  (Yoast → Social → Twitter). Affects every social share card.

## Phase 2 — Security headers + supply-chain (code PR) ◐
Closes the biggest code-side gap. **This PR.**

- New mu-plugin `wp-content/mu-plugins/firstchurch-security-headers.php` sends
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and a minimal
  `Permissions-Policy` on front-end responses.
- Wired into `ops/deploy.sh` in the same PR (mu-plugins aren't covered by the
  `check-deploy-coverage.sh` guardrail — it only scans `plugins/` and `themes/`
  — so a missing deploy line would ship nothing while CI stays green).
- CI gains a `composer audit` job across the Composer-bearing plugins; a
  `.github/dependabot.yml` keeps Composer deps and GitHub Actions current.
- **Out of scope here:** HSTS (set at the Cloudflare edge — Phase 0 finding) and
  a full Content-Security-Policy (the parent theme's inline styles make CSP a
  larger, report-only-first project — see Phase 6).
- **Verify after merge:** `curl -I https://firstchurchseattle.org` shows the new
  headers; CI green.

## Phase 3 — Front-end performance, low-risk slice (code PR) ◐
Quick perf wins fully inside the child theme.

- Append `&display=swap` to the parent theme's Google Fonts URL (via the
  `ctfw_google_fonts_style_url` filter) to eliminate flash-of-invisible-text.
- Add `preconnect` resource hints for `fonts.googleapis.com` /
  `fonts.gstatic.com` (via `wp_resource_hints`).
- **Lazy-loading — audit conclusion:** WordPress core already adds
  `loading="lazy"` to images in post content (`wp_filter_content_tags`). The
  remaining images are theme-template ones (banner/hero, sermon thumbnails)
  rendered outside `the_content` — those are frequently the LCP element, where
  forcing `lazy` *hurts*. So we deliberately do **not** add a blanket lazy-load
  filter; any per-template lazy-loading should be applied deliberately, not
  globally. No change shipped for lazy-loading.
- **Verify:** re-run Lighthouse, compare to the Phase 0 baseline; confirm no FOIT.

## Phase 4 — Caching & images (config + possible code) ☐
Highest-leverage perf move, but more moving parts → its own phase.

- Enable Cloudflare caching rules + image optimization (WebP/AVIF) at the edge.
- Decide whether to keep or replace Bluehost's `endurance-page-cache`.
- If edge optimization isn't viable, fall back to a WP image-optimization plugin
  (then it must be tracked + wired into `deploy.sh`).
- **Risk:** caching can mask dynamic bits — test the connection-card nonce flow,
  the MCP endpoints, and logged-in views behind the cache before trusting it.

## Phase 5 — SEO structured data (code PR) ◐
Discovery improvements for content Yoast doesn't already cover.

- **Sermons (shipped):** emit `VideoObject` JSON-LD on single `ctc_sermon` pages
  that have a video, reusing the tracked `ctfw_sermon_data()` helper. Yoast emits
  Article/WebPage but not VideoObject (a premium add-on), so this is
  complementary, not duplicative.
- **Events (deferred — needs a decision):** the `fce_event` CPT is registered
  `public => false`, so events have **no individual public URLs** — they surface
  via the Happenings spine and the `/events.ics` feed. `Event` JSON-LD therefore
  has no canonical single page to attach to. Options to revisit: emit an
  `ItemList` of upcoming `Event` nodes on whichever page lists events, or make
  the CPT public with single templates first. Left out of this PR deliberately.
- **Verify:** Google Rich Results Test on a live sermon URL.

## Phase 6 — Longer-term modernization (separate, optional track) ◐
Bigger lifts, intentionally walled off so they never gate the cheap wins above.

- **First-party JS modernization (shipped).** The child theme now has a
  buildless native-ES-module foundation: WordPress Script Modules API
  (`inc/scripts.php`), a progressive-enhancement "islands" pattern under
  `assets/js/`, and a dev-only test stack — Vitest (unit, in CI) + Playwright
  (browser, local-only) + Biome (lint/format, in CI). The inline `wp_head`
  skip-link script was replaced by a module (`assets/js/islands/skip-link.js`),
  and `happenings-block.js` was modernized to ES2022. See the child theme README
  ("First-party JavaScript"). Note: Playwright e2e stays **local-only** — it
  needs a running WordPress, which CI deliberately doesn't provision.
- Reduce the parent theme's jQuery dependency (Superfish / MeanMenu / jQuery
  Validate, loaded render-blocking in `<head>`) — large, regression-prone,
  touches navigation. Its own project with its own baseline. (Our own code is now
  jQuery-free; this is purely the vendored parent.)
- Full Content-Security-Policy — start report-only. Removing the inline
  skip-link script (above) cleared one of our own inline `<script>` blocks, so
  the remaining blockers are parent-theme inline styles/scripts, not ours.
- Add PHPCS + the WordPress-Coding-Standards ruleset to CI (the JS side already
  has Biome).

---

### Sequencing
- Phases 1–3 are independent and parallelizable (Phase 1 is config-only and can
  proceed while the Phase 2/3 PRs are in review).
- Phase 4 depends on the Phase 0 Cloudflare finding.
- Phase 6 is deferred so the heavy jQuery/CSP work never blocks the quick wins.
