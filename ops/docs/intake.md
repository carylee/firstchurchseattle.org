# Intake funnel — operations runbook

How the events/announcements intake funnel runs in production, and the few things
an operator has to do. For the *why* / design, see [`intake-spine.md`](./intake-spine.md).

```
 email ──▶ firstchurchnews worker ──┐
 (Cloudflare Email Routing)          │  POST /firstchurch/v1/intake/item
                                     ▼
 Breeze "Event Request Form" ──▶  fc_intake queue (one Item per submission)
 (hourly poll, already built)        │
                                     ▼  15-min WP-Cron: fcbf_intake_process_run()
                          classify → dismiss internal noise
                                   └─ extract → dedup → DRAFT event/announcement
                                              → set-intake-status(drafted, note, confidence)
                                     ▼
                          human reviews drafts in wp-admin → publish
```

The same **church voice** (`fc_church_voice()` in `firstchurch-mcp-abilities/voice.php`)
drives both the intake drafting and the block-editor "Rewrite in church voice" button.

## The one hard dependency: a provider Connector

Every AI step calls the **WordPress 7.0 core AI Client** (`wp_ai_client_prompt()`),
which needs an AI **provider Connector** configured. **Nothing AI-powered works
without it** — calls return `WP_Error "No models found that support text_generation"`.

- Provider plugin: **AI Provider for Google** (`ai-provider-for-google`), Gemini.
- Key: **Settings → Connectors → Google** (`/wp-admin/options-connectors.php`), or
  the `GOOGLE_API_KEY` env var (env/constant beats the DB value).
- It's **per-environment**: set it on prod *and* on local ddev separately. A DB-stored
  local key is wiped by `ddev pull-prod`; prefer the env var locally
  (`ddev config --web-environment-add="GOOGLE_API_KEY=…" && ddev restart`).

The provider plugin is **third-party** — it is *not* in `ops/deploy.sh`, so installing
it on prod is a manual one-time step:
```bash
ssh firstchurch 'cd ~/public_html && wp plugin install ai-provider-for-google --activate'
# then add the key at Settings → Connectors → Google
```

## First-time prod bring-up (after the intake code deploys)

1. Confirm the provider Connector is set on prod (above).
2. **Drain the backlog under supervision** — don't wait for the cron the first time:
   ```bash
   # preview what it would do
   ssh firstchurch 'cd ~/public_html && wp eval "echo wp_json_encode( wp_get_ability(\"firstchurch/process-intake\")->execute([\"limit\"=>5]) );"'
   # then a small batch, review the drafts in wp-admin, repeat
   ```
3. From then on the **15-min WP-Cron** (`fcbf_intake_process_event`) drains new items
   automatically; review drafts in the WordPress drafts dashboard.

## Day-to-day

- **Nothing** — capture (email + Breeze) and processing run on their own. Review the
  resulting drafts and publish.
- Inspect the queue: the `firstchurch/list-intake` ability, or **Intake** in wp-admin.
- Each processed Item carries `note` + `confidence` meta (why it was dismissed / how
  sure the draft was), surfaced via `get-intake`.

## Backdating historical content

New drafts are stamped at their **historical** date automatically: a past event to its
event date, an old announcement to its submission date (future events are left at "now"
so they don't get *scheduled*). To fix drafts created *before* this existed:
```bash
# dry run, then apply
ssh firstchurch 'cd ~/public_html && wp eval "echo wp_json_encode( wp_get_ability(\"firstchurch/backfill-intake-dates\")->execute([\"dry_run\"=>true]) );"'
ssh firstchurch 'cd ~/public_html && wp eval "echo wp_json_encode( wp_get_ability(\"firstchurch/backfill-intake-dates\")->execute([]) );"'
```
`backfill-intake-dates` is idempotent and only touches draft/pending posts.

## The dumb email worker (`../firstchurchnews`)

Pure transport: parses the email, stashes attachments in R2, POSTs the raw Item to
`/firstchurch/v1/intake/item` (Application Password on the `mcp-editor` user). No LLM,
no key. Deploy with `wrangler deploy` from that repo (it has no GitHub remote, so it
ships outside the WordPress merge-to-deploy flow). Route the intake address →
**Send to a Worker** → `firstchurchnews`.

## Abilities (all MCP-public, gated to `edit_posts`)

| Ability | What |
|---|---|
| `firstchurch/process-intake` | Run the processor over new items (manual drain). |
| `firstchurch/backfill-intake-dates` | One-time: backdate already-created drafts. |
| `firstchurch/intake-classify` · `intake-extract` | The classify / extract steps (used by the processor; callable directly). |
| `firstchurch/rewrite-in-voice` · `suggest-title` · `draft-excerpt` | Authoring assists (block editor + agents). |
| `firstchurch/guide-church-voice` | MCP resource: the house voice, one source of truth. |

## Notes

- **AI Services plugin is optional.** The block-editor button uses a custom REST route
  (`/firstchurch/v1/voice/rewrite`) → the `rewrite-in-voice` ability → the core AI
  Client. AI Services (its JS Prompt Builder) is only worth adding if/when we want a
  richer in-editor AI surface; it is not required for what ships today.
- **MCP Adapter:** pinned to canonical `WordPress/mcp-adapter` via
  `ops/scripts/install-mcp-adapter.sh` (the old `Automattic/wordpress-mcp` was archived
  Jan 2026).
- No AI key lives in WordPress code or `wp-config` — it's in the Connectors store.
