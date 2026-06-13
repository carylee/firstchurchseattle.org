<?php
/**
 * Intake admin UX — make the queue actually triage-able.
 *
 * fc_intake posts are always post_status 'publish' (the real triage state lives
 * in _fc_intake_status meta), so WordPress's native All/Published filter bar is
 * meaningless here. This file:
 *   - replaces that bar with triage-status views (New / Drafted / Dismissed +
 *     counts) and filters the list by the chosen one;
 *   - adds a Note column (the dismiss reason / draft note + confidence);
 *   - adds Dismiss / Re-queue row actions;
 *   - shows a provenance back-link on the event/post drafts created from intake.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Count items in a given triage status. */
function fcbf_intake_status_count(string $status): int
{
    $q = new WP_Query([
        'post_type'      => FCBF_INTAKE_CPT,
        'post_status'    => ['publish', 'pending', 'draft', 'private'],
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'meta_query'     => [['key' => FCBF_INTAKE_STATUS, 'value' => $status]],
    ]);
    return (int) $q->found_posts;
}

/* ---- Replace the post_status filter bar with triage-status views ---- */
add_filter('views_edit-' . FCBF_INTAKE_CPT, static function ($views) {
    $base    = admin_url('edit.php?post_type=' . FCBF_INTAKE_CPT);
    $current = isset($_GET['fc_status']) ? sanitize_key((string) $_GET['fc_status']) : '';
    $link    = static function (string $key, string $label, ?int $n) use ($base, $current): string {
        $url = '' === $key ? $base : add_query_arg('fc_status', $key, $base);
        $cls = ( $current === $key ) ? ' class="current" aria-current="page"' : '';
        $cnt = null === $n ? '' : ' <span class="count">(' . number_format_i18n($n) . ')</span>';
        return sprintf('<a href="%s"%s>%s%s</a>', esc_url($url), $cls, esc_html($label), $cnt);
    };

    $out = [
        'all'       => $link('', 'All', fcbf_intake_status_count('new') + fcbf_intake_status_count('drafted') + fcbf_intake_status_count('dismissed')),
        'new'       => $link('new', 'New', fcbf_intake_status_count('new')),
        'drafted'   => $link('drafted', 'Drafted', fcbf_intake_status_count('drafted')),
        'dismissed' => $link('dismissed', 'Dismissed', fcbf_intake_status_count('dismissed')),
    ];
    if (isset($views['trash'])) {
        $out['trash'] = $views['trash']; // keep WordPress's Trash view
    }
    return $out;
});

/* ---- Filter the list query by the chosen triage status ---- */
add_action('pre_get_posts', static function (WP_Query $q): void {
    if (!is_admin() || !$q->is_main_query()) {
        return;
    }
    if (FCBF_INTAKE_CPT !== ($q->get('post_type') ?: '')) {
        return;
    }
    $s = isset($_GET['fc_status']) ? sanitize_key((string) $_GET['fc_status']) : '';
    if (in_array($s, FCBF_INTAKE_STATUSES, true)) {
        $q->set('meta_query', [['key' => FCBF_INTAKE_STATUS, 'value' => $s]]);
    }
});

/* ---- Note column (the dismiss reason / draft note + confidence) ---- */
add_filter('manage_' . FCBF_INTAKE_CPT . '_posts_columns', static function ($cols) {
    $out = [];
    foreach ($cols as $k => $v) {
        $out[$k] = $v;
        if ('fcbf_status' === $k) {
            $out['fcbf_note'] = 'Note';
        }
    }
    return $out;
}, 20);

add_action('manage_' . FCBF_INTAKE_CPT . '_posts_custom_column', static function ($col, $post_id): void {
    if ('fcbf_note' !== $col) {
        return;
    }
    $conf = get_post_meta($post_id, FCBF_INTAKE_CONFIDENCE, true);
    if ('' !== (string) $conf) {
        echo '<span title="AI confidence" style="color:#646970">' . esc_html(round((float) $conf * 100) . '%') . '</span> ';
    }
    $note = (string) get_post_meta($post_id, FCBF_INTAKE_NOTE, true);
    echo esc_html(mb_strimwidth($note, 0, 90, '…'));
}, 20, 2);

/* ---- Row actions: Dismiss / Re-queue ---- */
add_filter('post_row_actions', static function ($actions, $post) {
    if (FCBF_INTAKE_CPT !== $post->post_type || !current_user_can('edit_posts')) {
        return $actions;
    }
    $status = (string) get_post_meta($post->ID, FCBF_INTAKE_STATUS, true) ?: 'new';
    $action_link = static function (string $to, string $label) use ($post): string {
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=fcbf_intake_setstatus&post=' . $post->ID . '&to=' . $to),
            'fcbf_intake_setstatus_' . $post->ID
        );
        return sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
    };
    $extra = [];
    if ('dismissed' !== $status) {
        $extra['fcbf_dismiss'] = $action_link('dismissed', 'Dismiss');
    }
    if ('new' !== $status) {
        $extra['fcbf_requeue'] = $action_link('new', 'Re-queue');
    }
    return array_merge($actions, $extra);
}, 10, 2);

add_action('admin_post_fcbf_intake_setstatus', static function (): void {
    $post_id = (int) ($_GET['post'] ?? 0);
    $to      = sanitize_key((string) ($_GET['to'] ?? ''));
    if (!current_user_can('edit_posts') || !wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), 'fcbf_intake_setstatus_' . $post_id)) {
        wp_die('You are not allowed to do that.', '', ['response' => 403]);
    }
    if (in_array($to, FCBF_INTAKE_STATUSES, true)) {
        fcbf_intake_ability_set_status([
            'id'     => $post_id,
            'status' => $to,
            'note'   => 'Manually marked ' . $to . ' by ' . wp_get_current_user()->user_login . '.',
        ]);
    }
    wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=' . FCBF_INTAKE_CPT));
    exit;
});

/* ---- Provenance back-link on the drafts created from intake ---- */
add_action('add_meta_boxes', static function ($post_type, $post): void {
    if (!in_array($post_type, ['fce_event', 'post'], true) || !($post instanceof WP_Post)) {
        return;
    }
    $items = get_posts([
        'post_type'      => FCBF_INTAKE_CPT,
        'post_status'    => ['publish', 'pending', 'draft', 'private'],
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'meta_query'     => [['key' => FCBF_INTAKE_LINKED, 'value' => (int) $post->ID]],
    ]);
    if (!$items) {
        return;
    }
    $item_id = (int) $items[0];
    add_meta_box(
        'fcbf_intake_source',
        'Intake source',
        static function () use ($item_id): void {
            $src     = (string) get_post_meta($item_id, FCBF_INTAKE_SOURCE, true);
            $contact = json_decode((string) get_post_meta($item_id, FCBF_INTAKE_CONTACT, true), true);
            $from    = is_array($contact) && !empty($contact['email']) ? $contact['email'] : '';
            $edit    = get_edit_post_link($item_id);

            echo '<p>Drafted from intake item ';
            echo $edit ? '<a href="' . esc_url($edit) . '">#' . $item_id . '</a>' : '#' . $item_id;
            echo ' <span class="description">(' . esc_html($src) . ( '' !== $from ? ' · ' . esc_html($from) : '' ) . ')</span>.</p>';

            $note = (string) get_post_meta($item_id, FCBF_INTAKE_NOTE, true);
            if ('' !== $note) {
                echo '<p class="description">' . esc_html($note) . '</p>';
            }
        },
        $post_type,
        'side',
        'low'
    );
}, 10, 2);
