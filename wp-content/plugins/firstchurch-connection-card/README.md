# First Church Connection Card

The **Check-in & Connection Card** — rendered natively in the theme and
submitted server-side to Breeze (form `320238`). This is **Mode 3** in the
Breeze-forms taxonomy: unlike `firstchurch-breeze-forms` (a themed button or an
official auto-sizing embed pointing at the public form URL), this plugin renders
the form as real theme markup and **mirrors Breeze's private AJAX endpoints** so
the visitor never leaves the page or sees an iframe. That's worth the extra
fragility for the one form people fill out on a phone mid-service.

> Every other church form should use `firstchurch-breeze-forms` — it needs no
> credentials and makes no network calls at render time. Reach for this plugin
> only when in-theme, in-place submission is the requirement.

## Usage

```
[firstchurch_connection_card]                       Default heading
[firstchurch_connection_card heading="Welcome!"]    Custom heading (empty = none)
```

The form lives on the **`/connection-card`** page (the shortcode in its body) and
is the target of the lobby carousel's connection-card QR callout
(`firstchurch-carousel` seed). The shortcode enqueues its own CSS/JS (vanilla, no
dependencies) and, when Turnstile is configured, Cloudflare's `api.js`.

## Returning-member prefill (device-side)

Members fill this out on their own phone every Sunday, and the *stable* half of
the card — name, email, phone, address, member status, newsletter opt-in —
almost never changes. To make the weekly check-in routine rather than a fresh
12-field form, `connection-card.js` remembers those fields **on the device** (a
single `localStorage` key, `fcc_profile_v1`) on a successful submit and restores
them on the next visit. The *transient* half (attendance, prayer/comments,
learn-more, pastor-contact) is deliberately **never** persisted — it starts
blank each week.

This is purely client-side: no login, no server-side identity, and nothing sent
to WordPress or Breeze beyond what the form already submits. A small "Welcome
back" banner makes the prefill visible and offers a one-tap **"Not you? Start
fresh"** escape for a shared family phone (clears the saved profile and resets
the form). If `localStorage` is unavailable (private mode), the feature degrades
silently to the old blank-form behavior.

## How submission works

`fcc_submit()` (the `POST /wp-json/firstchurch/v1/connection-card` handler)
mirrors what the browser does against Breeze's form backend, in three calls:

1. **Seed a session** — `GET /form/<slug>` to collect Breeze's cookies.
2. **Mint an entry id** — `POST /ajax/new_entry_id` with name/email; Breeze
   returns a numeric id.
3. **Save the section** — `POST /ajax/person_save_section` with the full
   `inputs_json` payload built by `fcc_build_inputs()`.

A self-minted `x-csrf-token` cookie + matching `X-CSRF-Token` header satisfies
Breeze's CSRF check. Every failure path is logged (see below) and returns a
`502`/`400` `WP_Error` whose message the JS surfaces inline.

### Field mapping (form 320238)

The Breeze field ids and option ids are the contract with the live form. They
live in `inc/form.php` and are pinned by `tests/OptionsTest.php`.

| Form field | Breeze field const | Notes |
|---|---|---|
| Attended (online/in-person) | `FCC_F_ATTENDED` | option ids `316`/`317` |
| First/last name | `FCC_F_NAME` | sent as a `name` field; `value`/`part` mirror Breeze's last-name fall-through |
| Email | `FCC_F_EMAIL` | required |
| Newsletter opt-in | `FCC_F_NEWSLETTER` | option `239`, only when checked |
| Phone | `FCC_F_PHONE` | optional |
| Address | `FCC_F_ADDRESS` | blank parts dropped; omitted if all blank |
| Change-of-info | `FCC_F_CHANGE_INFO` | option `240` |
| I am a… | `FCC_F_I_AM_A` | first/second-time/regular/member → `241`–`244` |
| Heard about us | `FCC_F_HEARD_FROM` | optional textarea |
| Learn more about… | `FCC_F_LEARN_MORE` | whitelisted option ids only |
| Pastor contact | `FCC_F_PASTOR` | `254` (call) / `255` (email) |
| Comments | `FCC_F_COMMENTS` | optional textarea |

## Anti-spam

The endpoint is public (anonymous worshippers submit on Sunday), defended in
layers:

- **Nonce** — `permission_callback` requires a valid `wp_rest` nonce. WP issues
  one to logged-out visitors via the rest cookie, so this proves the request came
  from a page that rendered the form, not a bare script.
- **Honeypot** — hidden `website`/`url` fields; if filled, the handler returns a
  fake success (`fcc_is_honeypot()`).
- **Rate limit** — 5 submissions per IP per 10 minutes (transient); the 6th gets
  a `429`, which the JS renders as a "wait a few minutes" message.
- **Turnstile (optional)** — Cloudflare Turnstile, off unless configured.

### Configuration

Turnstile is enabled by defining **both** constants in `wp-config.php`
(gitignored; never the DB):

```php
define( 'FCC_TURNSTILE_SITEKEY', 'your-sitekey' );
define( 'FCC_TURNSTILE_SECRET',  'your-secret'  );
```

With them set, the widget renders in the form and `fcc_verify_turnstile()` calls
Cloudflare's `siteverify` before the Breeze flow. Left undefined, the widget
never renders and verification is skipped — the form works as-is, with no secret
in the repo.

### Failure logging

Breeze failures fire `do_action( 'fcc_breeze_failure', $stage, $detail )` and,
under `WP_DEBUG`, write to the PHP error log (`fcc_log()`). Hook the action to
route failures to a monitor.

## Development / tests

`inc/form.php` is the pure core — the form contract, validation, and the params
→ Breeze `inputs` projection — with no HTTP and no WordPress state. The main
plugin file is the WP/HTTP shell. The suite runs outside WordPress; the
bootstrap shims the handful of `sanitize_*` / `is_email` helpers `inc/form.php`
uses.

```bash
cd wp-content/plugins/firstchurch-connection-card
composer install
vendor/bin/phpunit
vendor/bin/phpunit --filter BuildInputs   # one case
```

`vendor/` and `.phpunit.cache/` are gitignored; production never runs Composer
(the plugin `require_once`s `inc/form.php` directly, and `deploy.sh` excludes the
dev artifacts from the prod mirror).
