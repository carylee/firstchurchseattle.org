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
[breeze_form slug="603d6c56" mode="embed"]             Responsive iframe
[breeze_form slug="603d6c56" mode="embed" height="1000" max_width="720"]
[breeze_form id="1011854"]                              Resolve id→slug via the form list
```

The slug is the token after `/form/` in a form's public URL (find it in the
Breeze admin "Share" link, or in `../breeze/forms/**`). `slug` is canonical;
`id` is a convenience resolved through the form list (see "Form list" below).

### Attributes

| Attribute   | Default     | Applies to | Notes |
|-------------|-------------|------------|-------|
| `slug`      | —           | both       | Canonical form token. Required (or `id`). |
| `id`        | —           | both       | Numeric Breeze form id; resolved via the form list. |
| `mode`      | `button`    | both       | `button` (Mode 1) or `embed` (Mode 2). Unknown → button. |
| `label`     | `Open form` | button     | Button text. |
| `new_tab`   | `true`      | button     | Open the form in a new tab. |
| `title`     | `label`     | embed      | iframe accessible title. |
| `height`    | `800`       | embed      | Pixel height (cross-origin = no auto-height). |
| `max_width` | `680`       | embed      | Container max-width in px. |

Invalid/missing input renders nothing (never fatals the page).

## Form list (how the plugin knows which forms exist)

The list backing `id=` resolution (and, soon, an editor picker) comes from two
layers, resolved by `Store::resolve()`:

1. **Baked seed** — `data/forms.json`, committed with the plugin: a snapshot of
   the active forms (`{id, slug, name, folder_id}`). Always present, so the
   plugin works with zero configuration and survives any API outage. Guarded by
   `tests/SeedTest.php` (every slug must validate, ids unique).
2. **Runtime sync** *(in progress)* — a scheduled read from Breeze's read-only
   `list_forms` (Api-Key) cached in the `fcbf_synced_forms` option. When present
   it overrides the seed, so a form added in Breeze appears without a redeploy.
   Only written on a successful fetch, so a transient failure never blanks it.

Regenerate the seed offline when forms change substantially (the runtime sync
otherwise keeps things current). The current seed was generated from the Breeze
catalog under `../breeze/forms/active/`.

## Limitations (by design)

- **Embed height is fixed** — Breeze's page is cross-origin and posts no height
  messages, so the iframe can't auto-size. Set `height` per form.
- **Embedded payment forms can't be sandboxed** — Breeze's JS + Stripe need
  scripts/forms/same-origin/popups; the iframe is intentionally not sandboxed.

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
