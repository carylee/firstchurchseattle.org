<?php
/**
 * REST surface for the spine feed:
 *
 *   GET /wp-json/firstchurch/v1/happenings?weeks=8&days=30
 *
 * Returns the ordered Happening[] (events + announcements). Projects only
 * already-public content, so it is publicly readable — no secrets pass through.
 *
 * @package FirstChurch\Happenings
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', static function () {
    register_rest_route('firstchurch/v1', '/happenings', [
        'methods'             => 'GET',
        'callback'            => 'happenings_rest_feed',
        'permission_callback' => '__return_true',
        'args'                => [
            'weeks'   => ['type' => 'integer', 'default' => HAPPENINGS_DEFAULT_WEEKS, 'minimum' => 1, 'maximum' => 52],
            'days'    => ['type' => 'integer', 'default' => HAPPENINGS_DEFAULT_DAYS, 'minimum' => 1, 'maximum' => 365],
            // Reserved: which surface is asking. Per-surface filtering/curation
            // lands in a later phase; for now every surface gets the full feed.
            'surface' => ['type' => 'string', 'default' => 'all'],
        ],
    ]);
});

function happenings_rest_feed(WP_REST_Request $req): WP_REST_Response
{
    $items = happenings_resolve([
        'weeks' => (int) $req->get_param('weeks'),
        'days'  => (int) $req->get_param('days'),
    ]);

    return new WP_REST_Response([
        'surface'      => (string) $req->get_param('surface'),
        'count'        => count($items),
        'generated_at' => current_time('c'),
        'items'        => $items,
    ]);
}
