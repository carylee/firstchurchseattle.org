# First Church E-News — the weekly newsletter as a spine surface

Makes the weekly e-news an **authoring surface over the Happenings spine** instead of a
hand-built Mailchimp campaign — author an issue, render it to email, push it to Mailchimp as a
draft. See the design: [`ops/docs/enews-spine.md`](../../../ops/docs/enews-spine.md)
(roadmap **steps 6.3–6.5**).

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
│   ├── Email.php           # pure, unit-tested email render (card + document scaffold + footer)
│   └── Mailchimp.php       # pure, unit-tested Marketing-API payload/parse helpers
├── inc/
│   ├── cpt.php             # register enews_issue + its pre-fill block template
│   ├── meta.php            # subject / preview / send-date meta + the settings meta box
│   ├── render.php          # walk an issue's blocks → email HTML; the church footer; "Preview email"
│   └── mailchimp.php       # credentials + HTTP + "Push to Mailchimp draft" action
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

## Push to Mailchimp (draft, never auto-sent)

The **Push to Mailchimp** button in the *E-News Settings* box renders the issue and creates — or
updates, on re-push — a **draft** campaign via the Marketing API v3, then links to it. It never
sends: the irreversible step stays a human action in Mailchimp's UI after review. The campaign id
is remembered on the issue (so re-pushing updates one draft, recreating it if it 404s or was
already sent), and Mailchimp's own error detail is surfaced on failure. The email footer carries
the `*|UNSUB|*` / address merge tags Mailchimp requires to send.

Credentials live in **wp-config constants** (never committed):

```php
define( 'FCEN_MAILCHIMP_API_KEY',     'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us2' ); // the -us2 suffix is the datacenter
define( 'FCEN_MAILCHIMP_AUDIENCE_ID',  'xxxxxxxxxx' );  // Audience → Settings → Unique id
// optional:
define( 'FCEN_MAILCHIMP_FROM_NAME',    'First Church Seattle' );
define( 'FCEN_MAILCHIMP_REPLY_TO',     'comms@firstchurchseattle.org' );
```

Without the two required constants the button hides behind a one-line hint; nothing else breaks.

## Depends on

- **firstchurch-happenings** (the spine) — the timely sections are empty without it.
- The theme's **`firstchurch/happenings`** block (maranatha-child) — the composing block.

## Deploy / activation

Wired into `ops/deploy.sh`. After the first deploy, activate it, flush rewrites, and set the
Mailchimp constants in prod's `wp-config.php`:

```bash
ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-enews && wp rewrite flush'
```

## Not yet (follow-ups, see enews-spine.md §7)

- Projecting the evergreen "Recurring at First Church" list from a real source rather than
  seeding it as editable furniture.
- Inline-styling the editorial blocks (headings/paragraphs render as semantic HTML today; the
  scaffold sets a base font, which most clients honor — revisit if a client needs more).
- Optional: a one-click "schedule"/"send test" from WordPress (deliberately omitted — sending
  stays in Mailchimp).
