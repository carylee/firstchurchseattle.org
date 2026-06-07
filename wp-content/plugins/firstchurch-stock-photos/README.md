# First Church Stock Photos

Find free stock photos — from [Openverse](https://openverse.org) and [Pexels](https://pexels.com) —
and pull them into the WordPress media library, from the admin or from an AI agent, with full
provenance recorded on every import.

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
| **Admin** | *Tools ▸ Stock Photos* — search box (+ provider picker when more than one is configured), a results grid with dimensions, and a click-to-enlarge full-size preview; "Add to Library" on any image. No block-editor integration by design. |
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

## Providers

Search is pluggable: each provider registers a search adapter that returns one normalized
shape, and a dispatcher (`fcsp_search()`) routes to it. Import stays provider-agnostic.
Providers are equal peers — callers pass a `provider` (REST param, MCP `provider`, or the
admin picker); when omitted it defaults to `FCSP_DEFAULT_PROVIDER` (Openverse, which needs no
key). Add one by registering it on the `fcsp_providers` filter.

| Provider | Key required | License model |
|---|---|---|
| **Openverse** | No | Per-item CC / public-domain; filtered to commercial-use + modification, mature excluded. The attribution-safe default. |
| **Pexels** | `FCSP_PEXELS_API_KEY` | Uniform [Pexels License](https://www.pexels.com/license/) (free commercial use; attribution appreciated). Recorded as a generated credit string. |

To enable Pexels, get a free key at <https://www.pexels.com/api/> and add to `wp-config.php`:

```php
define( 'FCSP_PEXELS_API_KEY', '…' );
```

## Configuration — Openverse authentication

Works anonymously out of the box, but **anonymous requests are hard-capped at 20 results
per page** (Openverse returns `401` for any `page_size > 20`) plus a low rate limit. The
client clamps to 20 when unauthenticated, so search still works — it just can't pull larger
pages. Authenticating lifts the cap to **50/page** and raises the rate limit.

There's no signup web form — registration is itself an API call:

**1. Register an application** (one time):

```bash
curl -X POST https://api.openverse.org/v1/auth_tokens/register/ \
  -H 'Content-Type: application/json' \
  -d '{"name":"First Church Seattle","description":"Website stock photo search","email":"YOU@example.com"}'
# → { "client_id": "...", "client_secret": "...", "name": "...", "msg": "check your email ..." }
```

**2. Verify the email.** Openverse emails a verification link to that address. **Until you
click it, the credentials are still throttled at the anonymous tier (and still capped at
20/page)** — verification is what actually unlocks the authenticated limits.

**3. Add the credentials to `wp-config.php`** (out of git):

```php
define( 'FCSP_OPENVERSE_CLIENT_ID', '…' );
define( 'FCSP_OPENVERSE_CLIENT_SECRET', '…' );
```

The client then exchanges these for a short-lived bearer token automatically (cached as a
transient until just before it expires) — see `fcsp_openverse_token()`. No further code
changes needed.

Endpoints used: `POST /v1/auth_tokens/register/`, `POST /v1/auth_tokens/token/`
(`grant_type=client_credentials`), and `Authorization: Bearer <token>` on search requests.

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
