<?php
/**
 * MCP abilities — let an AI agent read and triage the intake queue.
 *
 * Three abilities under the existing `firstchurch` category (registered by the
 * firstchurch-mcp-abilities mu-plugin, which loads first). Typical flow: the
 * agent calls list-intake to see new submissions, get-intake for the full
 * detail of one, drafts an event/announcement via the existing create-*
 * abilities, then set-intake-status('drafted', linked_post=<new draft>) so the
 * item drops out of the queue and isn't re-drafted on the next pass.
 *
 * All three are gated to edit_posts (the mcp-editor identity): submissions carry
 * PII, so the read-only mcp-client is intentionally excluded. Registration is
 * guarded so the plugin is harmless if the Abilities API / MCP adapter aren't
 * present.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Promote the intake abilities to first-class tools on the MCP adapter's
 * default server (the same mechanism the mu-plugin and stock-photos use). */
add_filter(
    'mcp_adapter_default_server_config',
    static function ($config) {
        if (!is_array($config)) {
            return $config;
        }
        $existing        = isset($config['tools']) && is_array($config['tools']) ? $config['tools'] : [];
        $config['tools'] = array_values(
            array_unique(
                array_merge(
                    $existing,
                    ['firstchurch/list-intake', 'firstchurch/get-intake', 'firstchurch/set-intake-status']
                )
            )
        );
        return $config;
    }
);

/**
 * Shape one fc_intake post for the MCP surface.
 *
 * @return array<string,mixed>
 */
function fcbf_intake_to_array(WP_Post $post, bool $full = false): array
{
    $contact   = json_decode((string) get_post_meta($post->ID, FCBF_INTAKE_CONTACT, true), true);
    $responses = json_decode((string) get_post_meta($post->ID, FCBF_INTAKE_RESPONSES, true), true);
    $contact   = is_array($contact) ? $contact : [];
    $responses = is_array($responses) ? $responses : [];
    $linked    = (int) get_post_meta($post->ID, FCBF_INTAKE_LINKED, true);
    $note      = (string) get_post_meta($post->ID, FCBF_INTAKE_NOTE, true);
    $conf      = get_post_meta($post->ID, FCBF_INTAKE_CONFIDENCE, true);

    $data = [
        'id'         => $post->ID,
        'title'      => get_the_title($post),
        'source'     => (string) get_post_meta($post->ID, FCBF_INTAKE_SOURCE, true),
        'form_name'  => (string) get_post_meta($post->ID, FCBF_INTAKE_FORM_NAME, true),
        'form_id'    => (string) get_post_meta($post->ID, FCBF_INTAKE_FORM_ID, true),
        'created_on' => (string) get_post_meta($post->ID, FCBF_INTAKE_CREATED_ON, true),
        'status'     => (string) get_post_meta($post->ID, FCBF_INTAKE_STATUS, true) ?: 'new',
        'note'       => '' !== $note ? $note : null,
        'confidence' => '' !== $conf ? (float) $conf : null,
        'contact'    => [
            'name'  => (string) ($contact['name'] ?? ''),
            'email' => (string) ($contact['email'] ?? ''),
            'phone' => (string) ($contact['phone'] ?? ''),
        ],
        'linked_post' => $linked > 0 ? $linked : null,
        'edit_url'    => admin_url('post.php?post=' . $post->ID . '&action=edit'),
    ];

    if ($full) {
        $data['responses'] = array_map(
            static fn ($r) => ['label' => (string) ($r['label'] ?? ''), 'value' => (string) ($r['value'] ?? '')],
            $responses
        );
    } else {
        $data['response_count'] = count($responses);
    }

    return $data;
}

add_action(
    'wp_abilities_api_init',
    static function (): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        $mcp_public = ['mcp' => ['public' => true]];
        $can_edit   = static fn (): bool => current_user_can('edit_posts');

        wp_register_ability(
            'firstchurch/list-intake',
            [
                'label'               => 'List intake items',
                'description'         => 'List inbound requests captured from Breeze forms (publicity/event requests) awaiting triage. Each item is a submission to turn into an event or announcement draft. Defaults to status=new. Returns a contact summary, response count, status, edit URL, and any linked draft. Read-only — use get-intake for the full submission.',
                'category'            => 'firstchurch',
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [
                        'status'  => ['type' => 'string', 'enum' => ['new', 'drafted', 'dismissed', 'any'], 'default' => 'new'],
                        'source'  => ['type' => 'string', 'description' => 'Filter by source, e.g. "breeze".'],
                        'form_id' => ['type' => 'string', 'description' => 'Filter by Breeze form id.'],
                        'limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
                    ],
                    'additionalProperties' => false,
                ],
                'execute_callback'    => 'fcbf_intake_ability_list',
                'permission_callback' => $can_edit,
                'meta'                => array_merge($mcp_public, ['annotations' => ['readonly' => true, 'idempotent' => true]]),
            ]
        );

        wp_register_ability(
            'firstchurch/get-intake',
            [
                'label'               => 'Get intake item',
                'description'         => 'Get one intake item by id, including the full set of submitted question/answer responses and the submitter contact details. Read-only. Contact details are PII — use only to follow up, never publish them.',
                'category'            => 'firstchurch',
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => ['id' => ['type' => 'integer']],
                    'required'             => ['id'],
                    'additionalProperties' => false,
                ],
                'execute_callback'    => 'fcbf_intake_ability_get',
                'permission_callback' => $can_edit,
                'meta'                => array_merge($mcp_public, ['annotations' => ['readonly' => true, 'idempotent' => true]]),
            ]
        );

        wp_register_ability(
            'firstchurch/set-intake-status',
            [
                'label'               => 'Set intake status',
                'description'         => 'Mark an intake item new, drafted, or dismissed. When you create an event/announcement draft from an item, set status=drafted and pass linked_post (the new draft id) so it leaves the queue and is not re-drafted. Optionally record a note (e.g. why it was dismissed) and a 0–1 confidence.',
                'category'            => 'firstchurch',
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [
                        'id'          => ['type' => 'integer'],
                        'status'      => ['type' => 'string', 'enum' => FCBF_INTAKE_STATUSES],
                        'linked_post' => ['type' => 'integer', 'description' => 'The draft post created from this item (optional).'],
                        'note'        => ['type' => 'string', 'description' => 'Short triage note for a human (e.g. why dismissed, what was guessed).'],
                        'confidence'  => ['type' => 'number', 'minimum' => 0, 'maximum' => 1, 'description' => 'How sure the drafting was (optional).'],
                    ],
                    'required'             => ['id', 'status'],
                    'additionalProperties' => false,
                ],
                'execute_callback'    => 'fcbf_intake_ability_set_status',
                'permission_callback' => $can_edit,
                'meta'                => array_merge($mcp_public, ['annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => true]]),
            ]
        );
    }
);

/** @param array<string,mixed> $input */
function fcbf_intake_ability_list($input = [])
{
    $status = $input['status'] ?? 'new';
    $limit  = max(1, min(100, (int) ($input['limit'] ?? 25)));

    $meta_query = [];
    if ('any' !== $status) {
        // Items always carry an explicit status meta, so an equality match is exact.
        $meta_query[] = ['key' => FCBF_INTAKE_STATUS, 'value' => $status];
    }
    if (!empty($input['source'])) {
        $meta_query[] = ['key' => FCBF_INTAKE_SOURCE, 'value' => (string) $input['source']];
    }
    if (!empty($input['form_id'])) {
        $meta_query[] = ['key' => FCBF_INTAKE_FORM_ID, 'value' => (string) $input['form_id']];
    }

    $args = [
        'post_type'      => FCBF_INTAKE_CPT,
        'post_status'    => ['publish', 'pending', 'draft', 'private'],
        'posts_per_page' => $limit,
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    if ($meta_query) {
        $args['meta_query'] = count($meta_query) > 1 ? array_merge(['relation' => 'AND'], $meta_query) : $meta_query;
    }

    $q     = new WP_Query($args);
    $items = array_map(static fn (WP_Post $p) => fcbf_intake_to_array($p, false), $q->posts);

    return ['count' => count($items), 'items' => $items];
}

/** @param array<string,mixed> $input */
function fcbf_intake_ability_get($input)
{
    $post = get_post((int) ($input['id'] ?? 0));
    if (!$post || FCBF_INTAKE_CPT !== $post->post_type) {
        return new WP_Error('not_found', 'Intake item not found.');
    }

    return fcbf_intake_to_array($post, true);
}

/** @param array<string,mixed> $input */
function fcbf_intake_ability_set_status($input)
{
    $post = get_post((int) ($input['id'] ?? 0));
    if (!$post || FCBF_INTAKE_CPT !== $post->post_type) {
        return new WP_Error('not_found', 'Intake item not found.');
    }
    $status = (string) ($input['status'] ?? '');
    if (!in_array($status, FCBF_INTAKE_STATUSES, true)) {
        return new WP_Error('bad_status', 'Status must be one of: ' . implode(', ', FCBF_INTAKE_STATUSES) . '.');
    }

    update_post_meta($post->ID, FCBF_INTAKE_STATUS, $status);
    if (isset($input['linked_post'])) {
        $linked = (int) $input['linked_post'];
        if ($linked > 0) {
            update_post_meta($post->ID, FCBF_INTAKE_LINKED, $linked);
        } else {
            delete_post_meta($post->ID, FCBF_INTAKE_LINKED);
        }
    }
    if (isset($input['note'])) {
        $note = sanitize_textarea_field((string) $input['note']);
        if ('' !== $note) {
            update_post_meta($post->ID, FCBF_INTAKE_NOTE, $note);
        } else {
            delete_post_meta($post->ID, FCBF_INTAKE_NOTE);
        }
    }
    if (isset($input['confidence'])) {
        update_post_meta($post->ID, FCBF_INTAKE_CONFIDENCE, (string) (float) $input['confidence']);
    }

    return fcbf_intake_to_array($post, false);
}
