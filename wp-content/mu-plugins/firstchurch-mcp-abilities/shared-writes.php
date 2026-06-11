<?php
/**
 * First Church MCP Abilities — shared featured-image + recurrence.
 *
 * Cross-type writers: set-featured-image and set-event-recurrence (callbacks live in helpers.php).
 *
 * Loaded by ../firstchurch-mcp-abilities.php (WordPress does not auto-load
 * mu-plugin subdirectories). Procedural, global namespace, no autoloader —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$mcp_public    = array( 'mcp' => array( 'public' => true ) );

		/* ---- SHARED: FEATURED IMAGE + RECURRENCE ---- */

		wp_register_ability(
			'firstchurch/set-featured-image',
			array(
				'label'               => 'Set featured image',
				'description'         => 'Set the featured image of an event or announcement, either from an existing media library attachment (image_id) or by downloading an image URL (image_url).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						array( 'id' => array( 'type' => 'integer' ) ),
						fcmcp_image_schema()
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$id = (int) ( $input['id'] ?? 0 );
					if ( ! fcmcp_is_managed_post( $id ) ) {
						return new WP_Error( 'not_found', 'Only events and announcements are supported.' );
					}
					$res = fcmcp_set_featured_image( $id, $input );
					if ( is_wp_error( $res ) ) {
						return $res;
					}
					if ( null === $res ) {
						return new WP_Error( 'no_image', 'Provide image_id or image_url.' );
					}
					return array( 'id' => $id, 'attachment_id' => (int) $res, 'featured_image' => (string) get_the_post_thumbnail_url( $id, 'full' ) );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-event-recurrence',
			array(
				'label'               => 'Set event recurrence',
				'description'         => 'Set or clear the recurrence rule for an event (weekly/monthly/yearly, with interval, days/weeks, and end date). Set frequency=none to make it one-time.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'         => array( 'type' => 'integer' ),
						'recurrence' => fcmcp_recurrence_schema(),
					),
					'required'             => array( 'id', 'recurrence' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$id   = (int) ( $input['id'] ?? 0 );
					$post = get_post( $id );
					if ( ! $post || ! in_array( $post->post_type, array( 'ctc_event', 'fce_event' ), true ) ) {
						return new WP_Error( 'not_found', 'Event not found.' );
					}
					fcmcp_apply_recurrence( $id, $input['recurrence'] ?? array() );
					return array( 'id' => $id, 'recurrence' => fcmcp_recurrence_to_array( $id ) );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);
	}
);
