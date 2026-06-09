# First Church E-News — the weekly newsletter as a spine surface

Makes the weekly e-news an **authoring surface over the Happenings spine** instead of a
hand-built Mailchimp campaign. See the design: [`ops/docs/enews-spine.md`](../../../ops/docs/enews-spine.md)
(this is roadmap **step 6.3**).

## What it does

Registers one custom post type, **`enews_issue`** — a single weekly issue authored in the
block editor. The issue is a *thin curation layer*, not a content store:

- **Timely content projects from the spine.** The body is pre-filled with the theme's
  `firstchurch/happenings` blocks — a featured highlight, this week's events, and the last
  7 days of announcements — each rendered server-side from `firstchurch-happenings`. Edit an
  event once and the e-news is correct; nothing is re-keyed.
- **A new issue opens pre-filled, not blank.** The CPT's block `template` seeds the
  composition in order, so there's no "duplicate last week" ritual, and stale items are
  already gone (the spine self-expires). The template is unlocked — staff reorder and curate.
- **The hand-authored bits are small.** A Pastoral Message paragraph, the section headings,
  and the issue envelope (subject + preview tagline + send date, in the *E-News Settings*
  meta box). That's Bucket C in the design doc.

## Layout

```
firstchurch-enews/
├── firstchurch-enews.php   # bootstrap: CPT slug + meta keys, requires, rewrite flush
├── src/
│   └── Email.php           # pure, unit-tested email render (card + document scaffold)
├── inc/
│   ├── cpt.php             # register enews_issue + its pre-fill block template
│   ├── meta.php            # subject / preview / send-date meta + the settings meta box
│   └── render.php          # walk an issue's blocks → email HTML; staff "Preview email"
└── tests/                  # PHPUnit for src/ (dev-only, not deployed)
```

## Email render & preview

`fcen_render_email( $issue_id )` walks the issue's blocks: each `firstchurch/happenings` block
becomes a stack of email cards drawn from the **same spine lens** (`happenings_section_items`)
the `/engage` web block uses — so the email and the website agree on every section — and every
other block renders through WordPress. The result is wrapped in an inline-styled, table-based,
600px email scaffold (`src/Email.php`, the pure tested core). Staff preview a draft via the
**Preview email** link in the editor's Publish box (a nonced, edit-gated `admin-post` endpoint,
so it works for unpublished issues and is never public).

```bash
ddev exec 'cd wp-content/plugins/firstchurch-enews && composer install && vendor/bin/phpunit --testdox'
```

`vendor/`, `tests/`, `composer.*`, `phpunit.xml.dist` are dev-only — gitignored where applicable
and excluded from the deploy. Production loads `src/` via explicit `require_once` (no Composer).

## Depends on

- **firstchurch-happenings** (the spine) — the timely sections are empty without it.
- The theme's **`firstchurch/happenings`** block (maranatha-child) — the composing block.

## Deploy / activation

Wired into `ops/deploy.sh`. After the first deploy, activate it and flush rewrites once:

```bash
ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-enews && wp rewrite flush'
```

## Not yet (follow-ups, see enews-spine.md §7)

- **6.5** push the rendered issue to Mailchimp (campaign API / import-from-URL) for sending.
- Projecting the evergreen "Recurring at First Church" list from a real source rather than
  seeding it as editable furniture.
- Inline-styling the editorial blocks (headings/paragraphs render as semantic HTML today; the
  scaffold sets a base font, which most clients honor — revisit if a client needs more).
