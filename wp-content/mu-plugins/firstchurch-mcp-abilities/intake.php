<?php
/**
 * First Church MCP Abilities — REST intake endpoints.
 *
 * App-password-authenticated REST wrappers around the create abilities, for non-MCP automation (the e-news email worker).
 *
 * Loaded by ../firstchurch-mcp-abilities.php (WordPress does not auto-load
 * mu-plugin subdirectories). Procedural, global namespace, no autoloader —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------------------
 * Intake REST endpoints — a thin, app-password-authenticated wrapper around the
 * create abilities, for non-MCP automation (e.g. the firstchurchnews email
 * worker). One POST per draft; all date/time/recurrence/CTA normalization is
 * handled by the same fcmcp_create_* functions the MCP tools use.
 *
 *   POST /wp-json/firstchurch/v1/intake/event         (body = create-event input)
 *   POST /wp-json/firstchurch/v1/intake/announcement  (body = create-announcement input)
 *
 * Auth: HTTP Basic with an Application Password for an mcp_editor-role user.
 * Returns 201 with { id, status, edit_url, event|announcement }, or 4xx
 * { error, code }. Everything is created as a draft unless status says otherwise.
 * ------------------------------------------------------------------------- */

function fcmcp_rest_json_params( WP_REST_Request $req ): array {
	$p = $req->get_json_params();
	return is_array( $p ) ? $p : array();
}

function fcmcp_rest_intake_response( $result ) {
	if ( is_wp_error( $result ) ) {
		$status = ( 'not_found' === $result->get_error_code() ) ? 404 : 400;
		return new WP_REST_Response(
			array( 'error' => $result->get_error_message(), 'code' => $result->get_error_code() ),
			$status
		);
	}
	return new WP_REST_Response( $result, 201 );
}

add_action(
	'rest_api_init',
	static function () {
		$can_write = static function () {
			return current_user_can( 'edit_posts' );
		};

		register_rest_route(
			'firstchurch/v1',
			'/intake/event',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can_write,
				'callback'            => static function ( WP_REST_Request $req ) {
					$input = fcmcp_rest_json_params( $req );
					if ( '' === trim( (string) ( $input['title'] ?? '' ) ) ) {
						return new WP_REST_Response( array( 'error' => 'title is required.', 'code' => 'missing_title' ), 400 );
					}
					return fcmcp_rest_intake_response( fcmcp_create_event( $input ) );
				},
			)
		);

		register_rest_route(
			'firstchurch/v1',
			'/intake/announcement',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can_write,
				'callback'            => static function ( WP_REST_Request $req ) {
					$input = fcmcp_rest_json_params( $req );
					if ( '' === trim( (string) ( $input['title'] ?? '' ) ) || '' === trim( (string) ( $input['content'] ?? '' ) ) ) {
						return new WP_REST_Response( array( 'error' => 'title and content are required.', 'code' => 'missing_fields' ), 400 );
					}
					return fcmcp_rest_intake_response( fcmcp_create_announcement( $input ) );
				},
			)
		);
	}
);
