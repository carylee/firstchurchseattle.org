# First Church People

A first-party staff/people directory — the in-progress replacement for **Church Theme
Content**'s person type, the next CTC type to be re-owned after sermons (retired) and events
(`firstchurch-events`). See the migration map in
[`ops/docs/theme-independence.md`](../../../ops/docs/theme-independence.md).

## Approach: adopt `ctc_person` in place

Unlike events (which got a brand-new `fce_event` type), people **keeps CTC's type verbatim** —
the `ctc_person` post type, the `ctc_person_group` taxonomy, and the `_ctc_person_*` meta keys.
The dataset is tiny (~9 staff) and the live `/staff/<name>/` URLs matter, so adopting in place
means **zero data migration**: every existing person post, group term, headshot, and URL keeps
working untouched. The plugin just becomes the thing that *registers* the type once CTC no
longer does.

## Dormant until cutover (and why that's safe)

```
init:10  Church Theme Content registers ctc_person   ← while CTC is active, it wins
init:20  firstchurch-people: register ONLY if !post_type_exists('ctc_person')
```

While CTC is active we **no-op** — no double registration, no behaviour change. The moment CTC
is deactivated (the theme-independence endgame), our registration is the one that stands, with
the same name/slug/meta, and we `define( 'FCP_OWNS_PEOPLE', true )`.

`fcs_people_active()` reflects that flag. The two behaviour-changing surfaces gate on it:

- **Admin "Person details" metabox** (`inc/admin.php`) — hidden while CTC provides its own.
- **Child display templates** (in `maranatha-child`) — the live `/staff/` rendering stays on
  the theme until cutover, then our templates take over automatically.

**The exception — MCP authoring is live immediately.** `firstchurch/create-person` and
`update-person` (`inc/mcp.php`) write the same `ctc_person` posts + `_ctc_person_*` meta that
exist today, so agents can maintain the roster *now*, before the cutover. This closes the one
content type agents previously couldn't touch.

## What's here

```
firstchurch-people/
├── firstchurch-people.php   # type/taxonomy/meta consts; dormant-guarded registration; fcs_people_active()
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

Wired into `ops/deploy.sh` (mirror with `--delete`, dev artifacts excluded). After the first
deploy, activate and flush rewrites for the `/staff/` rule:

```sh
ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-people && wp rewrite flush'
```

> Activating does **not** flip the type away from CTC — the registration stays dormant until
> CTC is removed. It only turns on the live, additive pieces (MCP authoring). See the cutover
> checklist in `ops/docs/theme-independence.md`.
