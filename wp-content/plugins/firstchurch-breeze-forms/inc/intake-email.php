<?php
/**
 * Email intake — land a forwarded email (body + attachment links) as an
 * fc_intake post, so email rides the *same* queue as Breeze submissions.
 *
 * The capture is deliberately dumb: a small external Cloudflare worker parses
 * the MIME, stashes attachments in R2, and POSTs the raw pieces here. ALL the
 * understanding (classify, extract, voice, draft) happens later in WordPress,
 * over this queue — see the intake processor + the church-voice abilities.
 *
 * Endpoint (app-password auth, mcp_editor role — same gate as the mu-plugin's
 * intake.php create wrappers):
 *
 *   POST /wp-json/firstchurch/v1/intake/item
 *     { from, subject, body, message_id?, attachment_urls?: [], created_on? }
 *
 * Returns 201 { id, status:"new", edit_url } on capture, 200 { …, duplicate:true }
 * if the Message-ID was already seen, or 4xx { error, code }.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Has this email Message-ID already been captured? Checks all statuses
 * including trash, mirroring fcbf_intake_exists() — so a human-dismissed
 * (trashed) email is never resurrected by a re-delivery.
 */
function fcbf_intake_email_exists(string $message_id): bool
{
    if ($message_id === '') {
        return false; // no id → can't dedup; treat as new
    }
    $found = get_posts([
        'post_type'        => FCBF_INTAKE_CPT,
        'post_status'      => ['publish', 'pending', 'draft', 'private', 'future', 'trash'],
        'posts_per_page'   => 1,
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => true,
        'meta_query'       => [
            ['key' => FCBF_INTAKE_MSG_ID, 'value' => $message_id],
        ],
    ]);

    return !empty($found);
}

/**
 * Split a From header ("Jane Doe <jane@x.org>" or "jane@x.org") into name/email.
 *
 * @return array{name:string,email:string}
 */
function fcbf_intake_parse_from(string $from): array
{
    $from = trim($from);
    if (preg_match('/^\s*(.*?)\s*<([^>]+)>\s*$/', $from, $m)) {
        return ['name' => trim($m[1], " \"'"), 'email' => trim($m[2])];
    }
    return is_email($from) ? ['name' => '', 'email' => $from] : ['name' => $from, 'email' => ''];
}

/**
 * Insert one forwarded email as a new fc_intake post (status 'new').
 *
 * Reuses fcbf_intake_render_qa() for the human-readable editor view by shaping
 * the email as contact + a Subject/Body "Q&A", so the admin screen renders an
 * email exactly like a Breeze submission. Slashing matches fcbf_intake_insert().
 *
 * @param array{from?:string,subject?:string,body?:string,message_id?:string,
 *              attachment_urls?:array<int,string>,created_on?:string} $payload
 * @return int|WP_Error New post id, or an error.
 */
function fcbf_intake_insert_email(array $payload)
{
    $from    = (string) ($payload['from'] ?? '');
    $subject = trim((string) ($payload['subject'] ?? ''));
    $body    = trim((string) ($payload['body'] ?? ''));
    $msg_id  = (string) ($payload['message_id'] ?? '');

    // Keep well-formed http(s) attachment URLs (the worker stashes them in R2).
    // This only *stores* the reference — the actual download happens later via
    // download_url()/fcmcp_set_featured_image(), which carry their own SSRF
    // protection — so a format/scheme check is right here (not the network-
    // resolving wp_http_validate_url(), which would reject perfectly good URLs).
    $attachments = array_values(array_filter(array_map(
        static function ($u): string {
            $u = trim((string) $u);
            // Require an explicit http(s) scheme — esc_url_raw() would otherwise
            // prepend "http://" to scheme-less junk and let it through.
            return preg_match('#^https?://#i', $u) ? esc_url_raw($u, ['http', 'https']) : '';
        },
        (array) ($payload['attachment_urls'] ?? [])
    ), static fn (string $u): bool => $u !== ''));

    $contact = fcbf_intake_parse_from($from);

    $responses = [];
    if ($subject !== '') {
        $responses[] = ['label' => 'Subject', 'value' => $subject];
    }
    if ($body !== '') {
        $responses[] = ['label' => 'Message', 'value' => $body];
    }
    if ($attachments) {
        $responses[] = ['label' => 'Attachments', 'value' => implode("\n", $attachments)];
    }

    $title = $subject !== '' ? $subject : ($contact['email'] !== '' ? 'Email from ' . $contact['email'] : 'Email intake');

    $post_id = wp_insert_post(wp_slash([
        'post_type'    => FCBF_INTAKE_CPT,
        'post_status'  => 'publish', // CPT is private; status meta means "in the queue".
        'post_title'   => $title,
        'post_content' => fcbf_intake_render_qa(['contact' => $contact, 'responses' => $responses]),
    ]), true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $created_on = (string) ($payload['created_on'] ?? '');
    if ($created_on === '') {
        $created_on = gmdate('c');
    }

    update_post_meta($post_id, FCBF_INTAKE_SOURCE, 'email');
    update_post_meta($post_id, FCBF_INTAKE_CREATED_ON, $created_on);
    update_post_meta($post_id, FCBF_INTAKE_CONTACT, wp_slash((string) wp_json_encode($contact)));
    update_post_meta($post_id, FCBF_INTAKE_RESPONSES, wp_slash((string) wp_json_encode($responses)));
    update_post_meta($post_id, FCBF_INTAKE_STATUS, 'new');
    if ($msg_id !== '') {
        update_post_meta($post_id, FCBF_INTAKE_MSG_ID, $msg_id);
    }
    if ($attachments) {
        update_post_meta($post_id, FCBF_INTAKE_ATTACHMENTS, wp_slash((string) wp_json_encode($attachments)));
    }

    return (int) $post_id;
}

add_action(
    'rest_api_init',
    static function (): void {
        register_rest_route(
            'firstchurch/v1',
            '/intake/item',
            [
                'methods'             => 'POST',
                'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
                'callback'            => 'fcbf_intake_rest_capture',
            ]
        );
    }
);

/**
 * REST callback: capture a forwarded email as an intake Item.
 */
function fcbf_intake_rest_capture(WP_REST_Request $req)
{
    $p = $req->get_json_params();
    $p = is_array($p) ? $p : [];

    $subject = trim((string) ($p['subject'] ?? ''));
    $body    = trim((string) ($p['body'] ?? ''));
    if ($subject === '' && $body === '') {
        return new WP_REST_Response(
            ['error' => 'Provide at least a subject or body.', 'code' => 'empty_email'],
            400
        );
    }

    $msg_id = (string) ($p['message_id'] ?? '');
    if ($msg_id !== '' && fcbf_intake_email_exists($msg_id)) {
        return new WP_REST_Response(
            ['status' => 'duplicate', 'code' => 'already_captured', 'message_id' => $msg_id],
            200
        );
    }

    $id = fcbf_intake_insert_email($p);
    if (is_wp_error($id)) {
        return new WP_REST_Response(
            ['error' => $id->get_error_message(), 'code' => $id->get_error_code()],
            400
        );
    }

    return new WP_REST_Response(
        [
            'id'       => $id,
            'status'   => 'new',
            'edit_url' => get_edit_post_link($id, 'raw') ?: '',
        ],
        201
    );
}
