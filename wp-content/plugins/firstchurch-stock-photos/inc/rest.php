<?php
/**
 * REST routes backing the admin Tools page (and reusable for any first-party
 * caller). Both routes gate on the same capability as the rest of the plugin.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function () {
		$permission = static function () {
			return current_user_can( fcsp_capability() );
		};

		register_rest_route(
			'firstchurch/v1',
			'/stock-photos/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $permission,
				'args'                => array(
					'q'            => array( 'type' => 'string', 'required' => true ),
					'count'        => array( 'type' => 'integer', 'default' => 12 ),
					'page'         => array( 'type' => 'integer', 'default' => 1 ),
					'orientation'  => array( 'type' => 'string', 'default' => '', 'enum' => array( '', 'square', 'tall', 'wide' ) ),
					'license_type' => array( 'type' => 'string', 'default' => '' ),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$result = fcsp_search(
						array(
							'query'        => $request->get_param( 'q' ),
							'count'        => $request->get_param( 'count' ),
							'page'         => $request->get_param( 'page' ),
							'orientation'  => $request->get_param( 'orientation' ),
							'license_type' => $request->get_param( 'license_type' ),
						)
					);
					if ( is_wp_error( $result ) ) {
						return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 502 );
					}
					return rest_ensure_response( $result );
				},
			)
		);

		register_rest_route(
			'firstchurch/v1',
			'/stock-photos/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => $permission,
				'args'                => array(
					'image_url'   => array( 'type' => 'string', 'required' => true ),
					'title'       => array( 'type' => 'string' ),
					'alt'         => array( 'type' => 'string' ),
					'post_id'     => array( 'type' => 'integer' ),
					'openverse_id' => array( 'type' => 'string' ),
					'creator'     => array( 'type' => 'string' ),
					'creator_url' => array( 'type' => 'string' ),
					'license'     => array( 'type' => 'string' ),
					'license_url' => array( 'type' => 'string' ),
					'attribution' => array( 'type' => 'string' ),
					'source'      => array( 'type' => 'string' ),
					'foreign_url' => array( 'type' => 'string' ),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$result = fcsp_import( $request->get_params() );
					if ( is_wp_error( $result ) ) {
						$status = ( 'fcsp_forbidden' === $result->get_error_code() ) ? 403 : 400;
						return new WP_REST_Response( array( 'message' => $result->get_error_message() ), $status );
					}
					return rest_ensure_response( $result );
				},
			)
		);
	}
);
