<?php
/**
 * The `fc_intake` custom post type — the landing pad for inbound requests
 * (publicity / event submissions) so they become a triageable on-site queue
 * instead of living only in Breeze.
 *
 * One post per submission. The post title is the derived item title, the editor
 * holds a readable Q&A render for humans, and the structured payload + the
 * submitter's (private) contact details live in post meta. The type is
 * deliberately `public => false` and every meta key is `show_in_rest => false`:
 * submissions carry PII and must never be exposed publicly — the MCP
 * abilities (gated to edit_posts) and the admin list are the only read surfaces.
 *
 * The meta is source-agnostic (`_fc_intake_source`) so future email/flyer
 * intake lands in the very same queue. Today the only writer is the Breeze
 * entries reader (inc/intake-reader.php).
 */

if (!defined('ABSPATH')) {
    exit;
}

/** The intake queue post type. */
const FCBF_INTAKE_CPT = 'fc_intake';

/* Meta keys — referenced by literal string from the reader, the MCP abilities,
 * and the admin columns. Change one here and in all call sites together. */
const FCBF_INTAKE_SOURCE     = '_fc_intake_source';      // 'breeze' | 'email' | 'manual'
const FCBF_INTAKE_FORM_ID    = '_fc_intake_form_id';     // Breeze form id
const FCBF_INTAKE_FORM_NAME  = '_fc_intake_form_name';   // form display name
const FCBF_INTAKE_ENTRY_ID   = '_fc_intake_entry_id';    // Breeze entry id — DEDUP KEY
const FCBF_INTAKE_CREATED_ON = '_fc_intake_created_on';  // Breeze created_on
const FCBF_INTAKE_CONTACT    = '_fc_intake_contact';     // JSON {name,email,phone} (private)
const FCBF_INTAKE_RESPONSES  = '_fc_intake_responses';   // JSON [{label,value}]
const FCBF_INTAKE_STATUS     = '_fc_intake_status';      // 'new' | 'drafted' | 'dismissed'
const FCBF_INTAKE_LINKED     = '_fc_intake_linked_post'; // id of the draft created from this item
const FCBF_INTAKE_MSG_ID     = '_fc_intake_email_message_id'; // email Message-ID — DEDUP KEY (email source)
const FCBF_INTAKE_ATTACHMENTS = '_fc_intake_attachments';     // JSON [url,…] — links to stashed attachments
const FCBF_INTAKE_NOTE        = '_fc_intake_note';            // free-text triage note (e.g. why dismissed)
const FCBF_INTAKE_CONFIDENCE  = '_fc_intake_confidence';      // 0..1 — AI confidence when it drafted
const FCBF_INTAKE_ATTEMPTS    = '_fc_intake_attempts';        // processor failure count (parks the item after N)
const FCBF_INTAKE_DUP_OF      = '_fc_intake_dup_of';          // event id this item duplicates — a possible revision, NOT a reject
const FCBF_INTAKE_GAPS        = '_fc_intake_gaps';            // JSON [{field,question}] — per-field things the AI was unsure about

/** The triage states an intake item can be in. */
const FCBF_INTAKE_STATUSES = ['new', 'drafted', 'dismissed'];

add_action('init', 'fcbf_intake_register_cpt');

function fcbf_intake_register_cpt(): void
{
    register_post_type(
        FCBF_INTAKE_CPT,
        [
            'labels'          => [
                'name'          => 'Intake',
                'singular_name' => 'Intake Item',
                'menu_name'     => 'Intake',
                'all_items'     => 'Intake Queue',
                'edit_item'     => 'Intake Item',
                'view_item'     => 'Intake Item',
                'search_items'  => 'Search intake',
                'not_found'     => 'No intake items yet.',
            ],
            // Inbound requests with PII: no public single/archive, no REST, but a
            // full admin UI for triage. The MCP abilities are the agent's read path.
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'show_in_rest'    => false,
            // Dashicons ships no "inbox" glyph, so the old `dashicons-inbox`
            // class rendered blank in the admin menu (every other item had an
            // icon, Intake had none). Inline an SVG inbox/tray icon as a data
            // URI instead — guaranteed to render, tinted to the admin menu's
            // default icon color (#a7aaad).
            'menu_icon'       => 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#a7aaad">'
                . '<path d="M19 3H5c-1.1 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 '
                . '2-.9 2-2V5c0-1.1-.9-2-2-2zm0 12h-4c0 1.66-1.35 3-3 3s-3-1.34-3-3H5V5h14v10z"/>'
                . '</svg>'
            ),
            'menu_position'   => 26,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'supports'        => ['title', 'editor'],
        ]
    );

    $auth = static fn (): bool => current_user_can('edit_posts');
    foreach (
        [
            FCBF_INTAKE_SOURCE,
            FCBF_INTAKE_FORM_ID,
            FCBF_INTAKE_FORM_NAME,
            FCBF_INTAKE_ENTRY_ID,
            FCBF_INTAKE_CREATED_ON,
            FCBF_INTAKE_CONTACT,
            FCBF_INTAKE_RESPONSES,
            FCBF_INTAKE_STATUS,
            FCBF_INTAKE_LINKED,
            FCBF_INTAKE_MSG_ID,
            FCBF_INTAKE_ATTACHMENTS,
            FCBF_INTAKE_NOTE,
            FCBF_INTAKE_CONFIDENCE,
            FCBF_INTAKE_ATTEMPTS,
            FCBF_INTAKE_DUP_OF,
            FCBF_INTAKE_GAPS,
        ] as $key
    ) {
        register_post_meta(FCBF_INTAKE_CPT, $key, [
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => false, // PII — never over the public REST API
            'auth_callback' => $auth,
        ]);
    }
}

// Intake items are short structured records rendered as a Q&A summary — keep the
// classic editor so our HTML isn't reinterpreted by the block parser.
add_filter('use_block_editor_for_post_type', static function ($use, $type) {
    return FCBF_INTAKE_CPT === $type ? false : $use;
}, 10, 2);

// Intake is *ingested*, not hand-authored — drop the "Add New" affordance.
add_action('admin_menu', static function (): void {
    remove_submenu_page('edit.php?post_type=' . FCBF_INTAKE_CPT, 'post-new.php?post_type=' . FCBF_INTAKE_CPT);
}, 999);

/* ---- Admin list: surface the queue state at a glance ---- */

add_filter('manage_' . FCBF_INTAKE_CPT . '_posts_columns', static function ($cols) {
    $out = [];
    foreach ($cols as $k => $v) {
        if ('date' === $k) {
            $out['fcbf_form']      = 'Form';
            $out['fcbf_submitted'] = 'Submitted';
            $out['fcbf_status']    = 'Status';
            $out['fcbf_linked']    = 'Drafted';
        }
        $out[$k] = $v;
    }
    return $out;
});

add_action('manage_' . FCBF_INTAKE_CPT . '_posts_custom_column', static function ($col, $post_id): void {
    if ('fcbf_form' === $col) {
        echo esc_html((string) get_post_meta($post_id, FCBF_INTAKE_FORM_NAME, true));
    } elseif ('fcbf_submitted' === $col) {
        $on = (string) get_post_meta($post_id, FCBF_INTAKE_CREATED_ON, true);
        echo esc_html($on !== '' ? substr($on, 0, 16) : '—');
    } elseif ('fcbf_status' === $col) {
        $status = (string) get_post_meta($post_id, FCBF_INTAKE_STATUS, true) ?: 'new';
        echo '<span class="fcbf-intake-status fcbf-intake-status--' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span>';
    } elseif ('fcbf_linked' === $col) {
        $linked = (int) get_post_meta($post_id, FCBF_INTAKE_LINKED, true);
        if ($linked > 0) {
            $edit = get_edit_post_link($linked);
            echo $edit ? '<a href="' . esc_url($edit) . '">#' . (int) $linked . '</a>' : '#' . (int) $linked;
        } else {
            echo '—';
        }
    }
}, 10, 2);

/**
 * Render an intake record's contact + Q&A as escaped HTML for the post editor
 * (the human-readable view of a submission). Pure-ish: only depends on WP's
 * escaping primitives, which the test bootstrap shims.
 *
 * @param array{contact:array{name:string,email:string,phone:string},responses:array<int,array{label:string,value:string}>} $record
 */
function fcbf_intake_render_qa(array $record): string
{
    $contact = $record['contact'] ?? ['name' => '', 'email' => '', 'phone' => ''];
    $rows    = $record['responses'] ?? [];

    $html = '';

    $contactBits = array_filter([
        $contact['name'] ?? '',
        $contact['email'] ?? '',
        $contact['phone'] ?? '',
    ], static fn ($s) => (string) $s !== '');
    if ($contactBits) {
        $html .= '<p><strong>Submitted by:</strong> ' . esc_html(implode(' · ', $contactBits)) . '</p>';
    }

    if ($rows) {
        $html .= '<dl class="fcbf-intake-qa">';
        foreach ($rows as $row) {
            $html .= '<dt><strong>' . esc_html((string) ($row['label'] ?? '')) . '</strong></dt>';
            $html .= '<dd>' . nl2br(esc_html((string) ($row['value'] ?? ''))) . '</dd>';
        }
        $html .= '</dl>';
    }

    return $html;
}
