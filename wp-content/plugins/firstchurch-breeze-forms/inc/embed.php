<?php
/**
 * Breeze form embedding helpers — one source of truth for "put this sign-up form
 * on this post" used by the intake processor (auto-embed at draft creation) and
 * the Comms Desk review card (one-click embed / suggestion).
 *
 * @package FirstChurch\BreezeForms
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Extract a Breeze form id from a breezechms.com/form/<id> URL ('' if none). */
function fcbf_breeze_form_id_from_url(string $url): string
{
    return preg_match('#breezechms\.com/form/([A-Za-z0-9]+)#', $url, $m) ? $m[1] : '';
}

/**
 * Embed a Breeze sign-up form on a post by appending the [breeze_form] shortcode
 * (mode=embed — a responsive iframe that works for any form). Idempotent: a no-op
 * if that form is already embedded. Also records the form as the event's
 * registration_url when none is set.
 *
 * @return bool True if it embedded the form, false if skipped (already present / invalid).
 */
function fcbf_embed_breeze_form(int $post_id, string $form_id): bool
{
    $form_id = preg_replace('/[^A-Za-z0-9]/', '', $form_id);
    $post    = $post_id ? get_post($post_id) : null;
    if (!$post || '' === $form_id) {
        return false;
    }
    if (false !== strpos((string) $post->post_content, 'breeze_form id="' . $form_id . '"')) {
        return false; // already embedded
    }

    wp_update_post([
        'ID'           => $post_id,
        'post_content' => rtrim((string) $post->post_content) . "\n\n" . '[breeze_form id="' . $form_id . '" mode="embed"]' . "\n",
    ]);

    if ('' === (string) get_post_meta($post_id, '_fce_registration_url', true)) {
        update_post_meta($post_id, '_fce_registration_url', 'https://firstchurchseattle.breezechms.com/form/' . $form_id);
    }
    return true;
}
