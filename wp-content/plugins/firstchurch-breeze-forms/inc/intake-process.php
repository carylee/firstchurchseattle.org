<?php
/**
 * Intake processor — the brain that drains the fc_intake queue.
 *
 * For each `new` item: classify publicity-vs-internal (the church-voice ability),
 * dismiss room-booking/meeting noise, otherwise extract it into voice-corrected
 * event/announcement intents, dedup against existing upcoming events, create the
 * DRAFT(s) via the existing create-* functions, and mark the item drafted with a
 * link + confidence. Runs on a 15-min WP-Cron pass (the same real-crontab cadence
 * as the Breeze poll) and on demand via the firstchurch/process-intake ability.
 *
 * All the AI lives in the mu-plugin's church-voice abilities (fcmcp_intake_*,
 * fcmcp_create_*), which load first; this file is the orchestration + queue
 * bookkeeping only.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Cron hook that processes the intake queue. */
const FCBF_INTAKE_PROCESS_HOOK = 'fcbf_intake_process_event';

/** Park an item after this many failed extraction attempts (≈ this × 15 min). */
const FCBF_INTAKE_MAX_ATTEMPTS = 4;

/**
 * Record a failed extraction attempt and decide the item's fate. Used only for
 * failures that already SPENT an extraction call (bad model output, or a
 * successful-but-empty extraction) — these won't fix themselves, so after
 * FCBF_INTAKE_MAX_ATTEMPTS we park the item as dismissed (with a note, reversible)
 * instead of re-spending AI on it every cron pass. (Pure classify failures — a
 * down/unset Connector — are cheap and self-healing, so they are NOT counted and
 * keep retrying.)
 *
 * @return array{id:int,action:string,detail:string,drafts:array<int,int>}
 */
function fcbf_intake_attempt_failure(int $post_id, string $reason): array
{
    $n = (int) get_post_meta($post_id, FCBF_INTAKE_ATTEMPTS, true) + 1;
    update_post_meta($post_id, FCBF_INTAKE_ATTEMPTS, $n);

    if ($n >= FCBF_INTAKE_MAX_ATTEMPTS) {
        fcbf_intake_ability_set_status([
            'id'     => $post_id,
            'status' => 'dismissed',
            'note'   => sprintf('Auto-dismissed after %d attempts — %s. Needs a human (set status back to new to retry).', $n, $reason),
        ]);
        return ['id' => $post_id, 'action' => 'dismissed', 'detail' => "gave up after {$n}: {$reason}", 'drafts' => []];
    }
    return ['id' => $post_id, 'action' => 'error', 'detail' => sprintf('%s (attempt %d/%d, will retry)', $reason, $n, FCBF_INTAKE_MAX_ATTEMPTS), 'drafts' => []];
}

/** Flatten one intake item into a single text blob for the AI. */
function fcbf_intake_item_text(WP_Post $post): string
{
    $data  = fcbf_intake_to_array($post, true);
    $lines = [$data['title'] ?? ''];
    foreach (($data['responses'] ?? []) as $r) {
        $label = trim((string) ($r['label'] ?? ''));
        $value = trim((string) ($r['value'] ?? ''));
        if ($value === '') {
            continue;
        }
        $lines[] = ($label !== '' ? "{$label}: " : '') . $value;
    }
    return trim(implode("\n", array_filter($lines)));
}

/** The attachment URLs captured on an item (email source), if any. */
function fcbf_intake_item_attachments(int $post_id): array
{
    $raw = json_decode((string) get_post_meta($post_id, FCBF_INTAKE_ATTACHMENTS, true), true);
    return is_array($raw) ? array_values(array_filter(array_map('strval', $raw))) : [];
}

/**
 * Map a model-suggested category to a real event-category slug, or '' if none
 * fits. The model sometimes returns the display name ("Worship") or a near-slug;
 * match case-insensitively against the live taxonomy.
 */
function fcbf_intake_normalize_category(string $cat): string
{
    $cat = trim($cat);
    if ($cat === '') {
        return '';
    }
    $want = sanitize_title($cat); // "Worship" -> "worship"
    foreach ((array) get_terms(['taxonomy' => 'ctc_event_category', 'hide_empty' => false]) as $t) {
        if (!is_object($t)) {
            continue;
        }
        if ($t->slug === $want || sanitize_title($t->name) === $want) {
            return $t->slug;
        }
    }
    return '';
}

/**
 * A backdating publication date: the given YYYY-MM-DD if it's strictly in the
 * past, else '' (no backdate). Guards against passing a FUTURE date, which the
 * create-* functions would treat as "schedule to auto-publish" instead of draft.
 */
function fcbf_intake_backdate(string $date): string
{
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $m)) {
        return '';
    }
    return ($m[1] < gmdate('Y-m-d')) ? $m[1] : '';
}

/**
 * Conservative dedup: is there already an event with the same date and an
 * (essentially) identical title? Returns the existing event id, or 0.
 * Deliberately strict — showing two is better than wrongly merging two.
 */
function fcbf_intake_find_duplicate_event(string $title, string $date): int
{
    if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return 0;
    }
    $norm = static fn (string $s): string => preg_replace('/[^a-z0-9]+/', '', strtolower($s));
    $key  = $norm($title);
    if ($key === '') {
        return 0;
    }
    if (!function_exists('fcmcp_search_events')) {
        return 0;
    }
    $res = fcmcp_search_events(['from_date' => $date, 'to_date' => $date, 'status' => 'any', 'limit' => 50]);
    foreach ((array) ($res['events'] ?? []) as $ev) {
        // Titles can carry the "| When" suffix; compare the part before the bar.
        $existing = $norm(explode('|', (string) ($ev['title'] ?? ''))[0]);
        $incoming = $norm(explode('|', $title)[0]);
        if ($existing !== '' && $existing === $incoming) {
            return (int) ($ev['id'] ?? 0);
        }
    }
    return 0;
}

/**
 * Process one intake item end to end.
 *
 * @return array{id:int,action:string,detail:string,drafts:array<int,int>}
 */
function fcbf_intake_process_item(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post || FCBF_INTAKE_CPT !== $post->post_type) {
        return ['id' => $post_id, 'action' => 'skip', 'detail' => 'not an intake item', 'drafts' => []];
    }
    if ('new' !== ((string) get_post_meta($post_id, FCBF_INTAKE_STATUS, true) ?: 'new')) {
        return ['id' => $post_id, 'action' => 'skip', 'detail' => 'not new', 'drafts' => []];
    }
    if (!function_exists('fcmcp_intake_classify')) {
        return ['id' => $post_id, 'action' => 'skip', 'detail' => 'voice abilities unavailable', 'drafts' => []];
    }

    $text = fcbf_intake_item_text($post);
    if ($text === '') {
        return ['id' => $post_id, 'action' => 'skip', 'detail' => 'empty item', 'drafts' => []];
    }

    // 1. Classify. On AI failure, leave it 'new' to retry next pass.
    $class = fcmcp_intake_classify(['text' => $text]);
    if (is_wp_error($class)) {
        return ['id' => $post_id, 'action' => 'error', 'detail' => 'classify: ' . $class->get_error_message(), 'drafts' => []];
    }
    if (('publicity' !== ($class['class'] ?? '')) || ('none' === ($class['target'] ?? ''))) {
        fcbf_intake_ability_set_status([
            'id'         => $post_id,
            'status'     => 'dismissed',
            'note'       => 'Auto-dismissed (internal/not for the website): ' . (string) ($class['reason'] ?? ''),
            'confidence' => (float) ($class['confidence'] ?? 0),
        ]);
        return ['id' => $post_id, 'action' => 'dismissed', 'detail' => (string) ($class['reason'] ?? ''), 'drafts' => []];
    }

    // 2. Extract to voice-corrected intents.
    $created_on = (string) get_post_meta($post_id, FCBF_INTAKE_CREATED_ON, true);
    $received   = preg_match('/^(\d{4}-\d{2}-\d{2})/', $created_on, $m) ? $m[1] : gmdate('Y-m-d');
    $extract = fcmcp_intake_extract([
        'text'            => $text,
        'received_date'   => $received,
        'attachment_urls' => fcbf_intake_item_attachments($post_id),
    ]);
    if (is_wp_error($extract)) {
        // We spent an extraction call (e.g. unparseable model output) — count it
        // toward the give-up limit so a persistently bad item doesn't loop.
        return fcbf_intake_attempt_failure($post_id, 'extract: ' . $extract->get_error_message());
    }
    $intents = is_array($extract['intents'] ?? null) ? $extract['intents'] : [];
    $notes   = trim((string) ($extract['notes'] ?? ''));
    if (!$intents) {
        return fcbf_intake_attempt_failure($post_id, 'extraction produced no usable draft');
    }

    // 3. Create a draft per intent (dedup events conservatively).
    $drafts = [];
    $dup_of = 0; // an existing event this item duplicates — surfaced as a revision
    $conf   = 1.0;
    foreach ($intents as $intent) {
        $kind = (string) ($intent['kind'] ?? '');
        $conf = min($conf, (float) ($intent['confidence'] ?? 1));

        if ('event' === $kind && is_array($intent['event'] ?? null) && function_exists('fcmcp_create_event')) {
            $ev = $intent['event'];
            $dup = fcbf_intake_find_duplicate_event((string) ($ev['title'] ?? ''), (string) ($ev['start_date'] ?? ''));
            if ($dup > 0) {
                // Not a reject — a re-submission that may carry richer info. Hold the
                // link and surface it as a possible revision of the existing event.
                $dup_of = $dup;
                continue;
            }
            $ev['category'] = fcbf_intake_normalize_category((string) ($ev['category'] ?? ''));
            if ($ev['category'] === '') {
                unset($ev['category']);
            }
            // Backdate a PAST event's post to its event date so it reads as
            // historical. Never set a FUTURE date here — that would schedule the
            // post to auto-publish later instead of drafting it now.
            $ev['date'] = fcbf_intake_backdate((string) ($ev['start_date'] ?? ''));
            if ('' === $ev['date']) {
                unset($ev['date']);
            }
            $res = fcmcp_create_event($ev);
            if (!is_wp_error($res) && !empty($res['id'])) {
                $drafts[] = (int) $res['id'];
            }
        } elseif ('announcement' === $kind && is_array($intent['announcement'] ?? null) && function_exists('fcmcp_create_announcement')) {
            $ann = $intent['announcement'];
            // Backdate an old announcement to when it was submitted, so historical
            // news sits at its real moment rather than reading as today's news.
            $ann['date'] = fcbf_intake_backdate($received);
            if ('' === $ann['date']) {
                unset($ann['date']);
            }
            $res = fcmcp_create_announcement($ann);
            if (!is_wp_error($res) && !empty($res['id'])) {
                $drafts[] = (int) $res['id'];
            }
        }
    }

    if (!$drafts) {
        if ($dup_of > 0) {
            // A re-submission of an event already on the site. Surface it as a
            // possible revision (it may carry richer info) — linked, NOT dismissed.
            fcbf_intake_ability_set_status([
                'id'          => $post_id,
                'status'      => 'drafted',
                'linked_post' => $dup_of,
                'note'        => trim('Possible revision of event #' . $dup_of . ' — review whether this submission adds info. ' . $notes),
                'confidence'  => $conf,
            ]);
            update_post_meta($post_id, FCBF_INTAKE_DUP_OF, $dup_of);
            return ['id' => $post_id, 'action' => 'revision', 'detail' => 'possible revision of #' . $dup_of, 'drafts' => []];
        }
        // Genuinely nothing draftable — park it (don't loop forever).
        return fcbf_intake_attempt_failure($post_id, 'extraction produced no usable draft' . ($notes !== '' ? ' — ' . $notes : ''));
    }

    // 4. Mark drafted, link the first draft, carry notes + confidence.
    fcbf_intake_ability_set_status([
        'id'          => $post_id,
        'status'      => 'drafted',
        'linked_post' => $drafts[0],
        'note'        => $notes,
        'confidence'  => $conf,
    ]);

    return ['id' => $post_id, 'action' => 'drafted', 'detail' => $notes, 'drafts' => $drafts];
}

/**
 * Process up to $limit new items. Cron callback + manual entrypoint.
 *
 * @return array{processed:int,drafted:int,dismissed:int,errors:int,items:array<int,array<string,mixed>>}
 */
function fcbf_intake_process_run(int $limit = 20): array
{
    $q = new WP_Query([
        'post_type'      => FCBF_INTAKE_CPT,
        'post_status'    => ['publish', 'pending', 'draft', 'private'],
        'posts_per_page' => max(1, min(100, $limit)),
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'ASC', // oldest first — clear the backlog
        'meta_query'     => [['key' => FCBF_INTAKE_STATUS, 'value' => 'new']],
    ]);

    $out = ['processed' => 0, 'drafted' => 0, 'dismissed' => 0, 'errors' => 0, 'items' => []];
    foreach ($q->posts as $post) {
        $r = fcbf_intake_process_item($post->ID);
        $out['processed']++;
        if ('drafted' === $r['action']) {
            $out['drafted']++;
        } elseif ('dismissed' === $r['action']) {
            $out['dismissed']++;
        } elseif ('error' === $r['action']) {
            $out['errors']++;
        }
        $out['items'][] = $r;
    }
    return $out;
}

add_action(FCBF_INTAKE_PROCESS_HOOK, static function (): void {
    fcbf_intake_process_run(20);
});

/**
 * One-time backfill: re-stamp drafts created BEFORE backdating existed to their
 * historical date. Mirrors the live processor — an event backdates to its event
 * date (_fce_dtstart), an announcement to its intake submission date. Safe and
 * idempotent: only touches its own draft/pending linked posts, only sets
 * strictly-past dates (never schedules a future one), and skips a draft already
 * on its target date. Pass dry_run to preview.
 *
 * @return array{scanned:int,updated:int,skipped:int,items:array<int,array<string,mixed>>}
 */
function fcbf_intake_backfill_dates(int $limit = 200, bool $dry_run = false): array
{
    $q = new WP_Query([
        'post_type'      => FCBF_INTAKE_CPT,
        'post_status'    => ['publish', 'pending', 'draft', 'private'],
        'posts_per_page' => max(1, min(500, $limit)),
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => FCBF_INTAKE_STATUS, 'value' => 'drafted'],
            ['key' => FCBF_INTAKE_LINKED, 'compare' => 'EXISTS'],
        ],
    ]);

    $out = ['scanned' => 0, 'updated' => 0, 'skipped' => 0, 'items' => []];
    foreach ($q->posts as $item) {
        $out['scanned']++;
        $draft_id = (int) get_post_meta($item->ID, FCBF_INTAKE_LINKED, true);
        $draft    = $draft_id ? get_post($draft_id) : null;
        $note     = static function (string $r) use (&$out, $item, $draft_id): void {
            $out['skipped']++;
            $out['items'][] = ['item' => $item->ID, 'draft' => $draft_id ?: null, 'result' => $r];
        };

        if (!$draft) { $note('no linked draft'); continue; }
        if (!in_array($draft->post_status, ['draft', 'pending'], true)) {
            $note('skip (' . $draft->post_status . ' — left alone)');
            continue;
        }

        if ('fce_event' === $draft->post_type) {
            $target = fcbf_intake_backdate((string) get_post_meta($draft_id, '_fce_dtstart', true));
            $src    = 'event date';
        } else {
            $target = fcbf_intake_backdate((string) get_post_meta($item->ID, FCBF_INTAKE_CREATED_ON, true));
            $src    = 'submission date';
        }
        if ('' === $target) { $note('skip (no past date)'); continue; }

        $current = substr((string) $draft->post_date, 0, 10);
        if ($current === $target) { $note('already ' . $target); continue; }

        if (!$dry_run) {
            $r = wp_update_post(fcmcp_apply_post_date(['ID' => $draft_id], ['date' => $target]), true);
            if (is_wp_error($r)) { $note('error: ' . $r->get_error_message()); continue; }
        }
        $out['updated']++;
        $out['items'][] = [
            'item'   => $item->ID,
            'draft'  => $draft_id,
            'result' => ($dry_run ? 'WOULD set ' : 'set ') . $current . ' -> ' . $target . " ({$src})",
        ];
    }
    return $out;
}

/* ---- Manual trigger as an MCP ability (drain the queue on demand) ---- */

add_filter(
    'mcp_adapter_default_server_config',
    static function ($config) {
        if (is_array($config)) {
            $existing        = isset($config['tools']) && is_array($config['tools']) ? $config['tools'] : [];
            $config['tools'] = array_values(array_unique(array_merge($existing, ['firstchurch/process-intake', 'firstchurch/backfill-intake-dates'])));
        }
        return $config;
    }
);

add_action(
    'wp_abilities_api_init',
    static function (): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }
        wp_register_ability(
            'firstchurch/process-intake',
            [
                'label'               => 'Process the intake queue',
                'description'         => 'Run the intake processor over new items: classify, dismiss internal/noise, and create voice-corrected draft events/announcements for publicity items. Returns a per-item summary.',
                'category'            => 'firstchurch',
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    ],
                    'additionalProperties' => false,
                ],
                'execute_callback'    => static function ($input = []) {
                    return fcbf_intake_process_run((int) ($input['limit'] ?? 20));
                },
                'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
                'meta'                => ['mcp' => ['public' => true]],
            ]
        );

        wp_register_ability(
            'firstchurch/backfill-intake-dates',
            [
                'label'               => 'Backfill intake draft dates',
                'description'         => 'One-time: re-stamp already-created drafts to their historical publication date (events to their event date, announcements to their submission date) for items drafted before backdating existed. Idempotent; only touches draft/pending posts and strictly-past dates. Pass dry_run to preview.',
                'category'            => 'firstchurch',
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [
                        'limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 200],
                        'dry_run' => ['type' => 'boolean', 'default' => false, 'description' => 'Preview changes without applying them.'],
                    ],
                    'additionalProperties' => false,
                ],
                'execute_callback'    => static function ($input = []) {
                    return fcbf_intake_backfill_dates((int) ($input['limit'] ?? 200), (bool) ($input['dry_run'] ?? false));
                },
                'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
                'meta'                => ['mcp' => ['public' => true]],
            ]
        );
    }
);
