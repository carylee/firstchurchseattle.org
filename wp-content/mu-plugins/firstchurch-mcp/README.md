# MCP abilities — test harness

Tests for the **`../firstchurch-mcp-abilities.php`** mu-plugin (the First Church
MCP server: read + draft-first write abilities for events, sermons,
announcements, posts, pages, redirects, media). This directory is **dev/test
only** — it is *not* deployed (`ops/deploy.sh` ships the single mu-plugin file,
never this dir) and WordPress never loads it (mu-plugins only auto-loads
top-level `.php`).

## Running

```bash
composer install
vendor/bin/phpunit
```

Runs in CI as the **mcp-abilities** job. Like the other suites in this repo it
is **no-DB / no-WordPress**: `tests/bootstrap.php` (plus `wp-stubs.php`) defines
behavior-faithful shims for the handful of WP primitives the testable seams
touch — sanitizers, an in-memory post/meta/term store, capability checks — and
collectors for the registration hooks so the file loads and registers against a
fake Abilities API.

## What's covered

| Tier | File | Focus |
|---|---|---|
| 1 — contract | `AbilityContractTest`, `DirectToolsTest` | Every registered ability has a sound schema, callable callbacks, and `mcp.public`; `FCMCP_DIRECT_TOOLS` names only real abilities and the adapter filter merges/dedupes. |
| 2 — logic | `RecurrenceTest`, `DateTest`, `TermResolveTest` | The recurrence engine round-trip, date/status sanitizers + publication-date handling, term name/slug resolution with create-on-miss. |
| 2 — security | `PermissionTest` | Status-gated read `permission_callback`s, write capability gates, and the `map_meta_cap` closure that scopes the `mcp_editor` role to managed post types. |
| 3 — query | `QueryArgsTest` | The `fcmcp_build_*_query_args()` seams extracted from the search abilities. |

## Not covered (by design)

The create/update/trash **orchestration** (`wp_insert_post` + meta writes + the
CTC date recompute + publish/pending/future transitions) genuinely exercises
WordPress and isn't faithfully shimmable. That's a candidate for a separate,
opt-in real-WP integration suite (e.g. `wp-env`), kept out of the fast no-DB CI
— see the test-strategy notes.
