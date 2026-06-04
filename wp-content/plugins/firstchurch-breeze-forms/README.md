# First Church Breeze Forms

Surface any of the church's Breeze forms on the WordPress site with one
shortcode — as a **themed button** (Mode 1) or a **responsive embed** (Mode 2).
Both modes are pure markup pointing at the public Breeze form URL
(`https://firstchurchseattle.breezechms.com/form/<slug>`), so they need **no
Breeze credentials**, make **no network calls at render time**, and work for
*every* form including ones that take payment or file uploads.

> This is the low-fragility foundation. Native in-theme rendering + submission
> (Mode 3) lives in the separate `firstchurch-connection-card` plugin, which
> mirrors Breeze's private endpoints and is deliberately kept off this path.

## Usage

```
[breeze_form slug="603d6c56"]                          Button, "Open form"
[breeze_form slug="603d6c56" label="Contact us"]       Button, custom label
[breeze_form slug="603d6c56" new_tab="false"]          Open in same tab
[breeze_form slug="603d6c56" mode="embed"]             Auto-sizing embed
[breeze_form slug="603d6c56" mode="embed" button_color="92b765" max_width="720"]
[breeze_form id="1011854"]                              Resolve id→slug via the form list
```

The slug is the token after `/form/` in a form's public URL (find it in the
Breeze admin "Share" link, or in `../breeze/forms/**`). `slug` is canonical;
`id` is a convenience resolved through the form list (see "Form list" below).

### Editor block

Prefer not to type shortcodes? Insert the **Breeze Form** block: pick a form
from a searchable dropdown (populated from the synced list), toggle
button/embed and its options in the sidebar, and see a live preview. It's a
dynamic block — the front end renders through the same `Shortcode::render` path,
so block and shortcode produce identical markup. No JS build step; the editor
script is plain `wp.*` JavaScript.

### Attributes

| Attribute   | Default     | Applies to | Notes |
|-------------|-------------|------------|-------|
| `slug`      | —           | both       | Canonical form token. Required (or `id`). |
| `id`        | —           | both       | Numeric Breeze form id; resolved via the form list. |
| `mode`      | `button`    | both       | `button` (Mode 1) or `embed` (Mode 2). Unknown → button. |
| `label`     | `Open form` | button     | Button text. |
| `new_tab`   | `true`      | button     | Open the form in a new tab. |
| `max_width` | `680`       | embed      | Container max-width in px (height auto-sizes). |
| `background_color` | — | embed | Form background, hex (no `#`), e.g. `ffffff`. |
| `border_color`     | — | embed | Form border color, hex. |
| `border_width`     | — | embed | Form border width in px. |
| `button_color`     | — | embed | Submit-button color, hex, e.g. `92b765`. |

Invalid input is dropped silently (bad hex / non-numeric width are ignored);
missing required input renders nothing (never fatals the page).

### Mode 2 = Breeze's official embed

`mode="embed"` renders Breeze's own `breeze_form_embed` container and loads
their `form_embed.js`, which builds an **auto-resizing** iframe — so the embed
sizes to the form (no fixed height, no inner scrollbars) and honors the theming
params above. A `<noscript>` link is the no-JS fallback.

> **The form's logo/header is set in Breeze, not here.** A form's big header
> image and title bar are part of the Breeze form template; nothing our plugin
> (or any URL param) can do reaches inside the cross-origin iframe to hide them.
> To drop a redundant logo on embedded forms, clear the form's header image in
> the Breeze form editor.

## Form list (how the plugin knows which forms exist)

The list backing `id=` resolution (and, soon, an editor picker) comes from two
layers, resolved by `Store::resolve()`:

1. **Baked seed** — `data/forms.json`, committed with the plugin: a snapshot of
   the active forms (`{id, slug, name, folder_id}`). Always present, so the
   plugin works with zero configuration and survives any API outage. Guarded by
   `tests/SeedTest.php` (every slug must validate, ids unique).
2. **Runtime sync** — an hourly WP-Cron read from Breeze's read-only
   `list_forms` (Api-Key), cached in the `fcbf_synced_forms` option. When present
   it overrides the seed, so a form added in Breeze appears without a redeploy.
   Written only on a successful, non-empty fetch, so a transient failure or a
   bad response never blanks the list.

Regenerate the seed offline when forms change substantially (the runtime sync
otherwise keeps things current). The current seed was generated from the Breeze
catalog under `../breeze/forms/active/`.

**Descriptions.** Breeze has no description field, so each form's "description"
is the text of its first leading instructional field (paragraph/header). That
only comes from a per-form call, so it's fetched on a separate **daily** cron
(`fcbf_descriptions_event`) into the `fcbf_descriptions` option and merged into
each record. Only forms that lead with such text get one (~a quarter of them);
the rest are simply blank. There's no change signal from Breeze (no
ETag/Last-Modified/modified date — confirmed against the API), so the daily job
re-fetches everything; it keeps prior text on any per-form failure.

### Configuration & manual refresh

Add the read-only key to `wp-config.php` (gitignored; never the DB):

```php
define( 'FCBF_BREEZE_API_KEY', 'your-key' );
```

Without it the plugin runs fine on the baked seed; with it, the hourly sync keeps
the list live. Force a refresh any time:

```bash
ddev wp eval 'echo is_wp_error($e = fcbf_sync_run()) ? $e->get_error_message() : "ok";'
# or trigger the scheduled events:
ddev wp cron event run fcbf_sync_event           # form list (hourly)
ddev wp cron event run fcbf_descriptions_event   # descriptions (daily, ~1 call/form)
```

`fcbf_sync_run()` returns `true` or a `WP_Error` (e.g. `fcbf_no_key`,
`fcbf_http`, `fcbf_bad_body`) and only persists on success.

## Limitations (by design)

- **Embed requires JavaScript** — auto-sizing is driven by Breeze's
  `form_embed.js`; with JS off, the `<noscript>` fallback links to the form.
- **In-form chrome (logo/title) is a Breeze setting** — it lives inside the
  cross-origin iframe; remove it in the Breeze form editor, not here.
- **Embedded payment forms aren't sandboxed** — Breeze's JS + Stripe need
  scripts/forms/same-origin/popups; for payments/uploads, prefer the button.

## Development / tests

Pure PHP, red/green TDD. The core (`src/`) depends on only a handful of WP
escaping primitives, which `tests/bootstrap.php` shims faithfully so tests run
fast with no DB or network.

```bash
ddev test-breeze-forms                # whole suite
ddev test-breeze-forms --filter Url   # one unit
```

First run installs PHPUnit via Composer inside the web container. `vendor/` and
`.phpunit.cache/` are gitignored; production never runs Composer (the plugin
`require_once`s `src/` directly).
