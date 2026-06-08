# First Church Happenings — the spine

Projects the things happening at First Church into **one ordered `Happening[]` feed** that every
surface consumes. This is the "author once, project everywhere" spine described in
[`ops/docs/happenings.md`](../../../ops/docs/happenings.md).

## What it does

Two sources → one feed:

1. **Upcoming events** — `ctc_event` posts in the look-ahead window, with a human "when" string.
2. **Recent announcements** — posts in the `announcements` category in the look-back window,
   honoring the `fcs_weight` (prominence) and `fcs_expires` (lifecycle) meta.

Exposed two ways:

- **REST:** `GET /wp-json/firstchurch/v1/happenings?weeks=8&days=30` (public; projects only published content).
- **MCP:** `firstchurch/get-happenings` (read-only, on the shared `firstchurch` ability category).

Evergreen lobby-screen cards are **not** here — they belong to `firstchurch-carousel`, which
consumes this feed and mixes its cards in locally.

> **`firstchurch-carousel` depends on this plugin.** It calls `happenings_event_items()`,
> `happenings_news_items()`, `happenings_item_by_id()`, and the `happenings_item()/text()`
> helpers. Keep this plugin active.

## Layout

```
firstchurch-happenings/
├── firstchurch-happenings.php   # bootstrap: require src/ + inc/, constants
├── src/                         # pure, unit-tested projection logic (PSR-4, no WP)
│   ├── Id.php                   # parse "event-7" → {prefix,num}
│   ├── Item.php                 # build a feed item, dropping empty values
│   ├── Layout.php               # pick a card layout from an item's shape
│   ├── Text.php                 # decode WP entities + trim; clock detection
│   └── EventWhen.php            # format CTC date/recurrence/time → "Sundays at 10:30 am"
├── inc/                         # WordPress glue (WP_Query readers, REST, MCP)
│   ├── helpers.php              # procedural bridges into src/
│   ├── sources.php              # event + announcement readers → Happening[]
│   ├── resolve.php              # happenings_resolve() + happenings_item_by_id()
│   ├── rest.php                 # GET /v1/happenings
│   └── mcp.php                  # firstchurch/get-happenings
└── tests/                       # PHPUnit (red/green); dev-only, not deployed
```

## Development

Mirrors `firstchurch-breeze-forms`: the pure `src/` core is unit-tested with PHPUnit; the WP
glue is covered by the byte-identical carousel feed check.

```bash
ddev exec 'cd wp-content/plugins/firstchurch-happenings && composer install && vendor/bin/phpunit --testdox'
```

`vendor/`, `tests/`, `composer.*`, `phpunit.xml.dist`, and `.phpunit.cache/` are dev-only —
gitignored where applicable and excluded from the deploy (see `ops/deploy.sh`). Production never
runs Composer; the plugin loads `src/` via explicit `require_once`.
