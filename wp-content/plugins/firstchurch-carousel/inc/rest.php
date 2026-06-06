<?php
/**
 * REST surface for the resolved carousel feed:
 *
 *   GET /wp-json/firstchurch/v1/carousel?variant=preservice|postservice&weeks=8&days=30
 *
 * Returns the ordered Announcement[]-superset the slides pipeline consumes. The
 * feed projects already-public content (published events, announcements, and
 * carousel cards), so it is publicly readable — no secrets pass through it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', static function () {
	register_rest_route( 'firstchurch/v1', '/carousel', array(
		'methods'             => 'GET',
		'callback'            => 'fccar_rest_carousel',
		'permission_callback' => '__return_true',
		'args'                => array(
			'variant' => array(
				'type'    => 'string',
				'enum'    => array( 'preservice', 'postservice' ),
				'default' => 'preservice',
			),
			'weeks'   => array( 'type' => 'integer', 'default' => FCCAR_DEFAULT_WEEKS, 'minimum' => 1, 'maximum' => 52 ),
			'days'    => array( 'type' => 'integer', 'default' => FCCAR_DEFAULT_DAYS, 'minimum' => 1, 'maximum' => 365 ),
		),
	) );
} );

function fccar_rest_carousel( WP_REST_Request $req ): WP_REST_Response {
	$variant = (string) $req->get_param( 'variant' );
	$items   = fccar_resolve( array(
		'variant' => $variant,
		'weeks'   => (int) $req->get_param( 'weeks' ),
		'days'    => (int) $req->get_param( 'days' ),
	) );

	return new WP_REST_Response( array(
		'variant'      => $variant,
		'count'        => count( $items ),
		'generated_at' => current_time( 'c' ),
		'items'        => $items,
	) );
}
