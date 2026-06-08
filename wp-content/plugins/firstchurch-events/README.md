# First Church Events

A lean, RRULE-backed events backend — the in-progress replacement for Church Theme Content's
events (`church-theme-content` + the paid `church-content-pro`), which carries a tiny live load
(~10 upcoming, 8 recurrence rules) for a heavy dependency. Events are isolated behind the
Happenings spine's one boundary (`happenings_event_items()`), so this swaps in incrementally.

**Transitional:** the spine reads this **alongside** CTC until events are migrated — see
`ops/docs/events-migration.md`. Nothing here is destructive to CTC.

## Model

An event is a post (`fce_event`) with an anchor date + **CTC-shaped recurrence meta**. There is
**no roll-forward cron** — the RRULE is derived and occurrences are computed at query time with
[`rlanvin/php-rrule`](https://github.com/rlanvin/php-rrule). Storing the CTC shape lets two tested
helpers do the heavy lifting:

- `Recurrence::toRrule()` → the RRULE (for occurrences + the `.ics` feed).
- the spine's `\FirstChurch\Happenings\EventWhen::format()` → the human "when"
  ("Sundays at 10:30 am · Sanctuary", "Every 2nd & 4th Thursday").

`_fce_skip_dates` cancels individual occurrences (EXDATE) — filtered in PHP for the feed, emitted
as `EXDATE` in the `.ics`.

## Surfaces

- **Spine:** `fce_event_items()` returns the Happening shape; the spine merges it with CTC,
  date-sorted, so fce events appear on `/engage` and the carousel.
- **`/events.ics`:** a public subscription feed (VEVENT + RRULE + EXDATE) — calendar subscription,
  which CTC doesn't give us well.
- **Authoring:** MCP `firstchurch/create-event-lean` / `update-event-lean` (friendly recurrence
  object) **and** a light "Event details" editor metabox in wp-admin. Both write through the one
  `fce_write_event()`.

## Layout

```
firstchurch-events/
├── firstchurch-events.php   # CPT + meta consts; requires lib/ (runtime) + inc/
├── lib/rrule/               # vendored rlanvin/php-rrule v2.6.0 (MIT) — RUNTIME dep
├── src/Recurrence.php       # CTC-shaped fields → RRULE (TDD)
├── src/Ics.php              # iCalendar generator (TDD)
├── inc/event.php            # model: recurrence fields, RRULE/when derivation, fce_write_event()
├── inc/source.php           # fce_event_items() (spine Happening shape)
├── inc/mcp.php              # create/update-event-lean abilities
├── inc/admin.php            # the editor metabox
├── inc/feed.php             # /events.ics
└── tests/                   # PHPUnit (Recurrence, Ics)
```

## Deploy note

`rlanvin/php-rrule` is a **runtime** dependency and prod runs no Composer, so it's vendored under
`lib/` and required directly (the repo's no-composer-on-prod pattern). `ops/deploy.sh` ships `lib/`
but excludes the dev artifacts (Composer/PHPUnit). After the first deploy:
`ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-events'`.

## Dev

```bash
ddev exec 'cd wp-content/plugins/firstchurch-events && composer install && vendor/bin/phpunit --testdox'
```
