# First Church People

A first-party staff/people directory — the replacement for **Church Theme Content**'s person
type. See the migration map in
[`ops/docs/theme-independence.md`](../../../ops/docs/theme-independence.md).

## Approach: adopt `ctc_person` in place

Unlike events (which got a brand-new `fce_event` type), people **keeps CTC's type verbatim** —
the `ctc_person` post type, the `ctc_person_group` taxonomy, and the `_ctc_person_*` meta keys.
The dataset is tiny (~9 staff) and the live `/staff/<name>/` URLs matter, so adopting in place
means **zero data migration**: every existing person post, group term, headshot, and URL keeps
working untouched.

## Active displacement (not dormant)

```
init:5   firstchurch-people: remove_action('init','ctc_register_post_type_person')
         + remove_theme_support('ctc-people')
         → CTC's person registration is unhooked; its metaboxes/widgets disable themselves.
         ctc_event, ctc_sermon, and ctc_location are separate hooks — untouched.

init:10  CTC's ctc_register_post_type_person never fires (removed above).
         Other three types (ctc_event, ctc_sermon, ctc_location) register normally.

init:20  firstchurch-people: post_type_exists('ctc_person') is false → we register it.
         FCP_OWNS_PEOPLE is defined → fcs_people_active() returns true.
```

The plugin does not wait for full CTC decommission — it actively takes ownership of just the
person type. The other three CTC types remain registered by CTC until their own retirement.

`fcs_people_active()` gates the behaviour-changing surfaces:

- **Admin "Person details" metabox** (`inc/admin.php`) — active once we own the type.
- **Child display templates** (in the `firstchurch` theme) — `/staff/` archive and single profiles
  render via `staff-archive.php` / `person-single.php` automatically.

**MCP authoring is always live** — it was never gated.

## What's here

```
firstchurch-people/
├── firstchurch-people.php   # type/taxonomy/meta consts; active displacement; fcs_people_active()
├── src/Person.php           # pure, WP-free shaping: URL→icon mapping, tel: hrefs, pronoun normalise (TDD)
├── inc/person.php           # fcs_person_data() accessor, fcs_write_person() (the one writer), fcs_people_by_group()
├── inc/admin.php            # "Person details" metabox (gated) — saves via fcs_write_person()
├── inc/mcp.php              # create-person / update-person abilities (live; not gated)
├── tests/                   # PHPUnit for src/Person.php (run in CI)
├── composer.json / .lock    # dev-only PHPUnit; no runtime deps
└── phpunit.xml.dist
```

## Data model

| Piece | Stored as |
|---|---|
| Name | post title |
| Bio | post content (the "View Profile" body) |
| Headshot | featured image |
| Position | `_ctc_person_position` |
| Pronouns | `_ctc_person_pronouns` *(new — CTC had none; staff previously typed "[she/her]" into the name)* |
| Phone | `_ctc_person_phone` |
| Email | `_ctc_person_email` |
| Social / web links | `_ctc_person_urls` (one URL per line) |
| Group (Pastors / Staff) | `ctc_person_group` term |
| Order within group | `menu_order` |

## Tests

```sh
composer install
vendor/bin/phpunit
```

`src/Person.php` is pure PHP (no WordPress), tested WP-free like `firstchurch-events`. The
WordPress glue in `inc/` is thin and exercised on prod.

## Deploy / activation

Wired into `ops/deploy.sh` (mirror with `--delete`, dev artifacts excluded).

```
ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-people && wp rewrite flush'
```

> Activating the plugin triggers the active displacement immediately — CTC's person
> registration is unhooked and our metabox/templates take over. The plugin is already
> deployed and active; this step is only needed on first deploy.
