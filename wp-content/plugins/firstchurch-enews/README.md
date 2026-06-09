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
└── inc/
    ├── cpt.php             # register enews_issue + its pre-fill block template
    └── meta.php            # subject / preview / send-date meta + the settings meta box
```

No `src/`/tests: the plugin is WordPress registration glue (CPT + meta + a block template
array). The composing logic it leans on — the spine projection and the `firstchurch/happenings`
block — is tested in `firstchurch-happenings` and the theme.

## Depends on

- **firstchurch-happenings** (the spine) — the timely sections are empty without it.
- The theme's **`firstchurch/happenings`** block (maranatha-child) — the composing block.

## Deploy / activation

Wired into `ops/deploy.sh`. After the first deploy, activate it and flush rewrites once:

```bash
ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-enews && wp rewrite flush'
```

## Not yet (follow-ups, see enews-spine.md §7)

- **6.4** an email-safe (table/inlined-CSS) render of an issue + preview.
- **6.5** push that render to Mailchimp (campaign API / import-from-URL) for sending.
- Projecting the evergreen "Recurring at First Church" list from a real source rather than
  seeding it as editable furniture.
