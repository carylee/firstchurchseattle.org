<?php
/**
 * Breeze entries reader — poll `list_form_entries` for an allowlist of intake
 * forms and land each *new* submission as an fc_intake post.
 *
 * This is the credentialed edge + storage; the pure transform is src/Entries.php
 * and the CPT is inc/intake-cpt.php. It mirrors the existing fcbf_sync_* path
 * exactly: same wp_remote_get options, same WP_Error codes, same
 * "never act without the key, never blank on a transient failure" discipline.
 * Entries are only ever *inserted* (dedup'd by Breeze entry id, honoring
 * human-trashed items); existing items are never overwritten or deleted, so a
 * staffer's triage (status, edits, trash) always wins over the next poll.
 */

if (!defined('ABSPATH')) {
    exit;
}

use FirstChurch\BreezeForms\Entries;

/** Cron hook that polls Breeze form entries into the intake queue. */
const FCBF_INTAKE_HOOK = 'fcbf_intake_event';

/** Read-only public-API endpoint that lists a form's submissions. */
const FCBF_LIST_ENTRIES_URL = 'https://firstchurchseattle.breezechms.com/api/forms/list_form_entries';

/** Option holding the unix time of the last successful intake poll. */
const FCBF_INTAKE_LAST_SYNC_OPTION = 'fcbf_intake_last_sync';

/** Slugs of the publicity-intent forms ingested by default (filterable below). */
const FCBF_INTAKE_DEFAULT_SLUGS = ['event-request', 'event-request54'];

/**
 * The Breeze form ids to poll. Default = ids of the publicity/event-request
 * forms resolved from the synced catalog (robust to id changes); override via
 * the `fcbf_intake_form_ids` filter to add more publicity-type forms without a
 * code change. Sensitive forms (incident reports, prayer requests, emergency
 * contacts, …) are intentionally NOT in the default set.
 *
 * @return array<int,string> Breeze form ids.
 */
function fcbf_intake_form_ids(): array
{
    $ids = [];
    foreach (fcbf_records() as $record) {
        if (in_array($record['slug'] ?? '', FCBF_INTAKE_DEFAULT_SLUGS, true)) {
            $ids[] = (string) $record['id'];
        }
    }

    /**
     * Filter the allowlist of Breeze form ids ingested into the intake queue.
     *
     * @param array<int,string> $ids Default ids (the publicity/event-request forms).
     */
    $ids = apply_filters('fcbf_intake_form_ids', $ids);

    return array_values(array_unique(array_filter(array_map('strval', (array) $ids))));
}

/** Display name for a Breeze form id, from the synced catalog ('' if unknown). */
function fcbf_intake_form_name(string $form_id): string
{
    foreach (fcbf_records() as $record) {
        if ((string) $record['id'] === $form_id) {
            return (string) ($record['name'] ?? '');
        }
    }
    return '';
}

/** A credentialed GET against a Breeze endpoint, mirroring fcbf_sync_fetch's edge. */
function fcbf_intake_get(string $url)
{
    if (!defined('FCBF_BREEZE_API_KEY') || !FCBF_BREEZE_API_KEY) {
        return new WP_Error('fcbf_no_key', 'FCBF_BREEZE_API_KEY is not configured in wp-config.php.');
    }

    $resp = wp_remote_get($url, [
        'timeout'    => 20,
        'user-agent' => FCBF_USER_AGENT,
        'headers'    => ['Api-Key' => FCBF_BREEZE_API_KEY],
    ]);
    if (is_wp_error($resp)) {
        return $resp;
    }
    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        return new WP_Error('fcbf_http', "Breeze returned HTTP {$code} for {$url}.");
    }

    return (string) wp_remote_retrieve_body($resp);
}

/**
 * Fetch and normalize one form's submissions into intake records.
 *
 * @return array<int,array<string,mixed>>|WP_Error
 */
function fcbf_intake_fetch(string $form_id)
{
    // Field definitions (id→label/type) — the join key that makes responses readable.
    $fieldsBody = fcbf_intake_get(add_query_arg('form_id', $form_id, FCBF_LIST_FIELDS_URL));
    if (is_wp_error($fieldsBody)) {
        return $fieldsBody;
    }
    $fields = json_decode($fieldsBody, true);
    if (!is_array($fields)) {
        return new WP_Error('fcbf_bad_body', "Breeze list_form_fields returned an unparseable body for form {$form_id}.");
    }
    $map = Entries::field_map($fields);

    // The submissions themselves (details=1 so Breeze includes field context).
    $entriesBody = fcbf_intake_get(add_query_arg(['form_id' => $form_id, 'details' => '1'], FCBF_LIST_ENTRIES_URL));
    if (is_wp_error($entriesBody)) {
        return $entriesBody;
    }
    $raw = Entries::from_json($entriesBody);
    if ($raw === null) {
        return new WP_Error('fcbf_bad_body', "Breeze list_form_entries returned an unparseable body for form {$form_id}.");
    }

    return Entries::normalize($raw, $map, fcbf_intake_form_name($form_id));
}

/**
 * Has this Breeze entry already been ingested? Checks ALL statuses including
 * trash, so a human-dismissed (trashed) submission is never resurrected.
 */
function fcbf_intake_exists(string $entry_id, string $form_id): bool
{
    $found = get_posts([
        'post_type'        => FCBF_INTAKE_CPT,
        'post_status'      => ['publish', 'pending', 'draft', 'private', 'future', 'trash'],
        'posts_per_page'   => 1,
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => true,
        'meta_query'       => [
            'relation' => 'AND',
            ['key' => FCBF_INTAKE_ENTRY_ID, 'value' => $entry_id],
            ['key' => FCBF_INTAKE_FORM_ID, 'value' => $form_id],
        ],
    ]);

    return !empty($found);
}

/**
 * Insert one normalized record as a new fc_intake post (status 'new').
 *
 * @param array<string,mixed> $record
 * @return int|WP_Error New post id, or an error.
 */
function fcbf_intake_insert(array $record, string $form_id, string $form_name)
{
    // wp_insert_post() and update_post_meta() expect *slashed* input and run
    // wp_unslash() internally — so any backslash in the data (a literal '\' in
    // free text, or the '’' that wp_json_encode emits for a curly quote)
    // is stripped unless we wp_slash() first. Slash everything that can carry
    // arbitrary submission text so it round-trips intact.
    $post_id = wp_insert_post(wp_slash([
        'post_type'    => FCBF_INTAKE_CPT,
        'post_status'  => 'publish', // CPT is private; status just means "exists in the queue".
        'post_title'   => (string) ($record['title'] ?? 'Intake item'),
        'post_content' => fcbf_intake_render_qa($record),
    ]), true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    update_post_meta($post_id, FCBF_INTAKE_SOURCE, 'breeze');
    update_post_meta($post_id, FCBF_INTAKE_FORM_ID, $form_id);
    update_post_meta($post_id, FCBF_INTAKE_FORM_NAME, $form_name);
    update_post_meta($post_id, FCBF_INTAKE_ENTRY_ID, (string) ($record['entry_id'] ?? ''));
    update_post_meta($post_id, FCBF_INTAKE_CREATED_ON, (string) ($record['created_on'] ?? ''));
    update_post_meta($post_id, FCBF_INTAKE_CONTACT, wp_slash((string) wp_json_encode($record['contact'] ?? [])));
    update_post_meta($post_id, FCBF_INTAKE_RESPONSES, wp_slash((string) wp_json_encode($record['responses'] ?? [])));
    update_post_meta($post_id, FCBF_INTAKE_STATUS, 'new');

    return (int) $post_id;
}

/**
 * Poll every allowlisted form and ingest new submissions. Cron callback + the
 * manual entrypoint (`ddev wp eval 'print_r(fcbf_intake_run());'`).
 *
 * A per-form fetch failure is recorded but never aborts the whole run, and an
 * entry is inserted only when it doesn't already exist — so the poll is safe to
 * run repeatedly. On any successful, error-free run the last-sync stamp updates.
 *
 * @return array{inserted:int,skipped:int,forms:int,errors:array<int,string>}|WP_Error
 */
function fcbf_intake_run()
{
    if (!defined('FCBF_BREEZE_API_KEY') || !FCBF_BREEZE_API_KEY) {
        return new WP_Error('fcbf_no_key', 'FCBF_BREEZE_API_KEY is not configured in wp-config.php.');
    }

    $form_ids = fcbf_intake_form_ids();
    if (!$form_ids) {
        return new WP_Error('fcbf_no_forms', 'No intake forms resolved; nothing to poll.');
    }

    $inserted = 0;
    $skipped  = 0;
    $errors   = [];

    foreach ($form_ids as $form_id) {
        $records = fcbf_intake_fetch($form_id);
        if (is_wp_error($records)) {
            $errors[] = $form_id . ': ' . $records->get_error_message();
            continue;
        }

        $form_name = fcbf_intake_form_name($form_id);
        foreach ($records as $record) {
            $entry_id = (string) ($record['entry_id'] ?? '');
            if ($entry_id === '' || fcbf_intake_exists($entry_id, $form_id)) {
                $skipped++;
                continue;
            }
            $res = fcbf_intake_insert($record, $form_id, $form_name);
            if (is_wp_error($res)) {
                $errors[] = $form_id . '/' . $entry_id . ': ' . $res->get_error_message();
                continue;
            }
            $inserted++;
        }

        usleep(100000); // ~10 req/s — gentle on a small origin server (matches the descriptions sync).
    }

    // Only stamp a clean run; a run with fetch errors keeps the prior timestamp.
    if (!$errors) {
        update_option(FCBF_INTAKE_LAST_SYNC_OPTION, time(), false);
    }

    return ['inserted' => $inserted, 'skipped' => $skipped, 'forms' => count($form_ids), 'errors' => $errors];
}

add_action(FCBF_INTAKE_HOOK, 'fcbf_intake_run');
