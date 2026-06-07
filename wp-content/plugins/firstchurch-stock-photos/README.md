# First Church Stock Photos

Find attribution-safe, openly-licensed photos from [Openverse](https://openverse.org)
and pull them into the WordPress media library — from the admin or from an AI agent —
with full provenance recorded on every import.

This plugin is **fully ours** and runs in a **dual setup alongside Instant Images**:

- **Instant Images** stays installed as the in-editor picker humans browse (its media-modal
  tab + block-editor sidebar) — the one piece not worth rebuilding ourselves.
- **This plugin** owns the gaps II can't reach: the **MCP/agent path** (II has no clean
  server-side search/import API) and a standalone **Tools ▸ Stock Photos** admin screen, both
  via Openverse (no API key, no pro upsells, license/attribution baked into every result).

It also **ties the two together**: a `instant_images_after_upload` bridge records the same
provenance for II uploads, and code-level policy filters lock II's safe-search/attribution
config so it can't drift on prod. Nothing here deactivates or modifies Instant Images.

## What it does

| Surface | How |
|---|---|
| **Admin** | *Tools ▸ Stock Photos* — a plain search box + results grid; "Add to Library" on any image. No block-editor integration by design. |
| **AI agent (MCP)** | Abilities `firstchurch/search-stock-photo` and `firstchurch/import-stock-photo` (category `firstchurch`, promoted to first-class MCP tools). |
| **Programmatic** | REST: `GET firstchurch/v1/stock-photos/search`, `POST firstchurch/v1/stock-photos/import`. Or call `fcsp_search()` / `fcsp_import()` directly. |
| **Provenance** | Every import — **ours and Instant Images'** — stamps creator/license/attribution/source as `_fcsp_*` attachment meta, surfaced in a "Source" column in the Media library. `fcsp_attachment_credit( $id )` returns a credit line. |

## Policy baked in

**Our Openverse path:**
- Only results that **allow commercial use AND modification** are surfaced (`FCSP_DEFAULT_LICENSE_TYPE`), so editors never have to reason about license edge cases.
- **Mature content is excluded** at the API level.
- Access gated on `upload_files` (filter: `fcsp_capability`).

**Instant Images (`inc/policy.php`):** force safe search across providers (Unsplash content
filter, Openverse mature off, Pixabay safe search) and one attribution template — baked in
code rather than the prod settings screen. Tunable via the `FCSP_II_*` constants.

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
- **Leave Instant Images active** — this is a dual setup. The bridge and policy filters are
  no-ops if II is ever removed, so nothing breaks either way.
