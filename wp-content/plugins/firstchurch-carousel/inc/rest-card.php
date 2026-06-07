<?php
/**
 * REST: create or update a standing carousel_card straight from the Curate
 * drawer, so curators never have to leave the workbench to author the evergreen
 * cards the deck is built from.
 *
 *   POST /wp-json/firstchurch/v1/carousel/card
 *     body: { id?: "card-<n>", title, layout, body, prompt, details,
 *             qr_url, bg_color, preservice, image_id }
 *
 * `id` present + resolvable → update; otherwise create. Validation runs through
 * the same fccar_sanitize_card_input() the classic metabox uses, so the two
 * authoring paths can't drift. Returns the resolved feed item (so the drawer can
 * repaint the tile in place) plus its feed id and featured-image id.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', static function () {
	register_rest_route( 'firstchurch/v1', '/carousel/card', array(
		'methods'             => 'POST',
		'callback'            => 'fccar_rest_save_card',
		'permission_callback' => static function () {
			return current_user_can( 'edit_posts' );
		},
	) );
} );

function fccar_rest_save_card( WP_REST_Request $req ) {
	$raw   = (array) $req->get_json_params();
	$clean = fccar_sanitize_card_input( $raw );

	// A card needs at least something to say — otherwise it's a ghost tile.
	if ( '' === $clean['title'] && '' === $clean['body'] && '' === $clean['prompt'] && '' === $clean['details'] ) {
		return new WP_Error( 'fccar_empty_card', 'Add a title, body, prompt, or details before saving.', array( 'status' => 400 ) );
	}

	// Resolve an existing card to update, else create.
	$post_id = 0;
	$ref     = isset( $raw['id'] ) ? (string) $raw['id'] : '';
	if ( preg_match( '/^card-(\d+)$/', $ref, $m ) ) {
		$existing = get_post( (int) $m[1] );
		if ( $existing && FCCAR_CPT === $existing->post_type ) {
			$post_id = (int) $m[1];
		}
	}

	$postarr = array(
		'post_type'   => FCCAR_CPT,
		'post_status' => 'publish',
		'post_title'  => $clean['title'],
	);
	if ( $post_id ) {
		$postarr['ID'] = $post_id;
		$res           = wp_update_post( $postarr, true );
	} else {
		$res = wp_insert_post( $postarr, true );
	}
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'fccar_save_failed', $res->get_error_message(), array( 'status' => 500 ) );
	}
	$post_id = (int) $res;

	fccar_apply_card_meta( $post_id, $clean );

	// The drawer is authoritative over the background: a positive id sets it,
	// 0 clears it.
	if ( $clean['image_id'] > 0 ) {
		set_post_thumbnail( $post_id, $clean['image_id'] );
	} else {
		delete_post_thumbnail( $post_id );
	}

	return new WP_REST_Response( array(
		'ok'      => true,
		'id'      => 'card-' . $post_id,
		'imageId' => (int) get_post_thumbnail_id( $post_id ),
		'item'    => fccar_card_to_item( get_post( $post_id ) ),
	) );
}
