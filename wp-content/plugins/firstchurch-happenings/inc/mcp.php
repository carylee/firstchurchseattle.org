<?php
/**
 * MCP ability `firstchurch/get-happenings` — the spine feed exposed to AI
 * agents through the existing MCP server, the way they already read events and
 * announcements (see firstchurch-mcp-abilities.php). Registers under the shared
 * `firstchurch` ability category; guarded so it no-ops if the Abilities API is
 * absent.
 *
 * @package FirstChurch\Happenings
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_abilities_api_init', static function () {
    if (!function_exists('wp_register_ability')) {
        return;
    }

    wp_register_ability('firstchurch/get-happenings', [
        'label'               => 'Get happenings feed',
        'description'         => 'Get the resolved Happenings feed — upcoming events + recent announcements as one ordered list. Each item carries id/source/layout/title plus when/body/ctaUrl/image as applicable. Honors announcement weight (prominence) and expiry. Read-only.',
        'category'            => 'firstchurch',
        'input_schema'        => [
            'type'                 => 'object',
            'properties'           => [
                'weeks' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 52, 'default' => HAPPENINGS_DEFAULT_WEEKS, 'description' => 'Upcoming-events look-ahead window.'],
                'days'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => HAPPENINGS_DEFAULT_DAYS, 'description' => 'Recent-announcements look-back window.'],
            ],
            'additionalProperties' => false,
        ],
        'execute_callback'    => static function ($input = []) {
            $items = happenings_resolve([
                'weeks' => (int) ($input['weeks'] ?? HAPPENINGS_DEFAULT_WEEKS),
                'days'  => (int) ($input['days'] ?? HAPPENINGS_DEFAULT_DAYS),
            ]);
            return ['count' => count($items), 'items' => $items];
        },
        'permission_callback' => static function () {
            return current_user_can('read');
        },
        'meta'                => [
            'mcp'         => ['public' => true],
            'annotations' => ['readonly' => true, 'idempotent' => true],
        ],
    ]);
});
