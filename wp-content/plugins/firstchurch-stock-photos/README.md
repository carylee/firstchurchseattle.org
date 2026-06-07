# First Church Stock Photos

Find attribution-safe, openly-licensed photos from [Openverse](https://openverse.org)
and pull them into the WordPress media library — from the admin or from an AI agent —
with full provenance recorded on every import.

This plugin is **fully ours** and intentionally replaces the third-party *Instant Images*
plugin for this site. We only need free stock photos in two places (the admin and the MCP
server), and Openverse covers both without an API key, without pro upsells, and with
license/attribution metadata baked into every result — so there's nothing worth maintaining
a React media-modal integration for.

## What it does

| Surface | How |
|---|---|
| **Admin** | *Tools ▸ Stock Photos* — a plain search box + results grid; "Add to Library" on any image. No block-editor integration by design. |
| **AI agent (MCP)** | Abilities `firstchurch/search-stock-photo` and `firstchurch/import-stock-photo` (category `firstchurch`, promoted to first-class MCP tools). |
| **Programmatic** | REST: `GET firstchurch/v1/stock-photos/search`, `POST firstchurch/v1/stock-photos/import`. Or call `fcsp_search()` / `fcsp_import()` directly. |
| **Provenance** | Every import stamps creator/license/attribution/source as `_fcsp_*` attachment meta, surfaced in a "Source" column in the Media library. `fcsp_attachment_credit( $id )` returns a credit line. |

## Policy baked in

- Only results that **allow commercial use AND modification** are surfaced (`FCSP_DEFAULT_LICENSE_TYPE`), so editors never have to reason about license edge cases.
- **Mature content is excluded** at the API level.
- Access gated on `upload_files` (filter: `fcsp_capability`).

## Configuration

Works anonymously out of the box. To raise Openverse rate limits, register a client at
`https://api.openverse.org/v1/auth_tokens/register/` and add to `wp-config.php`:

```php
define( 'FCSP_OPENVERSE_CLIENT_ID', '…' );
define( 'FCSP_OPENVERSE_CLIENT_SECRET', '…' );
```

(Keep these out of git.)

## Agent flow

1. `firstchurch/search-stock-photo` → `{ query: "candlelight vigil" }` → list of candidates.
2. `firstchurch/import-stock-photo` → pass the chosen candidate's fields (`image_url`, `creator`,
   `license`, `attribution`, …) plus an optional `post_id` → returns `attachment_id`.
3. Or feed that `attachment_id` into the existing event/sermon/announcement abilities'
   `image_id` parameter.

## Deploy / ops

- Wired into `ops/deploy.sh` (fully ours → `--delete` mirror).
- After first deploy: `ssh firstchurch 'wp plugin activate firstchurch-stock-photos'`.
- Dropping Instant Images is a separate, manual prod step: `ssh firstchurch 'wp plugin deactivate instant-images'` (then remove if desired). This plugin does not touch it.
