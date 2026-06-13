<?php
/**
 * REST routes behind the Comms Desk review cards. Thin wrappers over work the
 * engine already does — approve = publish the linked draft; needs-info = record
 * a follow-up note on the intake item (the full clarification loop is a later
 * phase). "Tweak" needs no route: it links to the draft's editor, which already
 * has the "Rewrite in church voice" button.
 *
 * @package FirstChurch\CommsDesk
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function (): void {
		$can = static function () {
			return current_user_can( 'edit_posts' );
		};
		register_rest_route( 'firstchurch/v1', '/comms-desk/approve', array(
			'methods'             => 'POST',
			'permission_callback' => $can,
			'callback'            => 'fccd_rest_approve',
		) );
		register_rest_route( 'firstchurch/v1', '/comms-desk/needs-info', array(
			'methods'             => 'POST',
			'permission_callback' => $can,
			'callback'            => 'fccd_rest_needs_info',
		) );
		register_rest_route( 'firstchurch/v1', '/comms-desk/dismiss', array(
			'methods'             => 'POST',
			'permission_callback' => $can,
			'callback'            => 'fccd_rest_dismiss',
		) );
	}
);

/** Dismiss an intake item (e.g. a revision that adds nothing new). */
function fccd_rest_dismiss( WP_REST_Request $req ) {
	$p       = $req->get_json_params();
	$item_id = is_array( $p ) ? (int) ( $p['item_id'] ?? 0 ) : 0;
	if ( ! $item_id || ! function_exists( 'fcbf_intake_ability_set_status' ) ) {
		return new WP_REST_Response( array( 'error' => 'Bad request.' ), 400 );
	}
	$r = fcbf_intake_ability_set_status( array(
		'id'     => $item_id,
		'status' => 'dismissed',
		'note'   => 'Dismissed from the Comms Desk by ' . wp_get_current_user()->user_login . '.',
	) );
	if ( is_wp_error( $r ) ) {
		return new WP_REST_Response( array( 'error' => $r->get_error_message() ), 400 );
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/** Approve & publish: flip the linked draft to published. */
function fccd_rest_approve( WP_REST_Request $req ) {
	$p        = $req->get_json_params();
	$draft_id = is_array( $p ) ? (int) ( $p['draft_id'] ?? 0 ) : 0;
	$post     = $draft_id ? get_post( $draft_id ) : null;
	if ( ! $post ) {
		return new WP_REST_Response( array( 'error' => 'Draft not found.' ), 404 );
	}
	if ( ! current_user_can( 'publish_post', $draft_id ) ) {
		return new WP_REST_Response( array( 'error' => 'You are not allowed to publish this.' ), 403 );
	}
	$r = wp_update_post( array( 'ID' => $draft_id, 'post_status' => 'publish' ), true );
	if ( is_wp_error( $r ) ) {
		return new WP_REST_Response( array( 'error' => $r->get_error_message() ), 400 );
	}
	return new WP_REST_Response( array( 'ok' => true, 'status' => 'publish', 'view_url' => get_permalink( $draft_id ) ), 200 );
}

/** Needs info: record the follow-up question as a note on the intake item. */
function fccd_rest_needs_info( WP_REST_Request $req ) {
	$p        = $req->get_json_params();
	$item_id  = is_array( $p ) ? (int) ( $p['item_id'] ?? 0 ) : 0;
	$question = is_array( $p ) ? trim( (string) ( $p['question'] ?? '' ) ) : '';
	if ( ! $item_id || ! function_exists( 'fcbf_intake_ability_set_status' ) ) {
		return new WP_REST_Response( array( 'error' => 'Bad request.' ), 400 );
	}
	$note = 'Needs info' . ( '' !== $question ? ': ' . $question : '' ) . ' — flagged by ' . wp_get_current_user()->user_login . '.';
	$r    = fcbf_intake_ability_set_status( array( 'id' => $item_id, 'status' => 'drafted', 'note' => $note ) );
	if ( is_wp_error( $r ) ) {
		return new WP_REST_Response( array( 'error' => $r->get_error_message() ), 400 );
	}
	return new WP_REST_Response( array( 'ok' => true, 'note' => $note ), 200 );
}
