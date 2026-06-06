<?php
/**
 * MCP ability `firstchurch/get-carousel` — the same resolved feed, exposed to
 * AI agents / the slides Worker through the existing MCP server, the way they
 * already read events and announcements (see firstchurch-mcp-abilities.php and
 * CONSOLIDATION-PLAN.md §8). Registers under the shared `firstchurch` ability
 * category the mu-plugin defines; guarded so it no-ops if the Abilities API is
 * absent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_abilities_api_init', static function () {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability( 'firstchurch/get-carousel', array(
		'label'               => 'Get announcement carousel',
		'description'         => 'Get the resolved pre-/post-worship announcement carousel — an ordered list of cards assembled from evergreen carousel cards, upcoming events, and recent announcements. Each item carries a layout plus title/body/when/ctaUrl/image/preserviceOnly. Read-only.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'variant' => array(
					'type'        => 'string',
					'enum'        => array( 'preservice', 'postservice' ),
					'default'     => 'preservice',
					'description' => 'postservice drops preservice-only cards.',
				),
				'weeks'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 52, 'default' => FCCAR_DEFAULT_WEEKS, 'description' => 'Upcoming-events look-ahead window.' ),
				'days'    => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => FCCAR_DEFAULT_DAYS, 'description' => 'Recent-announcements look-back window.' ),
			),
			'additionalProperties' => false,
		),
		'execute_callback'    => static function ( $input = array() ) {
			$items = fccar_resolve( array(
				'variant' => $input['variant'] ?? 'preservice',
				'weeks'   => (int) ( $input['weeks'] ?? FCCAR_DEFAULT_WEEKS ),
				'days'    => (int) ( $input['days'] ?? FCCAR_DEFAULT_DAYS ),
			) );
			return array( 'count' => count( $items ), 'items' => $items );
		},
		'permission_callback' => static function () {
			return current_user_can( 'read' );
		},
		'meta'                => array(
			'mcp'         => array( 'public' => true ),
			'annotations' => array( 'readonly' => true, 'idempotent' => true ),
		),
	) );
} );
