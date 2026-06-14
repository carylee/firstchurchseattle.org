<?php
/**
 * REST routes behind the rich review-card controls: set a featured image (from
 * the media library or a stock-photo search), edit an announcement's CTA, and
 * embed/suggest a Breeze sign-up form on an event. All gated to edit_posts and
 * scoped to the linked draft; they reuse the existing engine functions
 * (fcsp_search/fcsp_import, fcbf_records, the fcs_cta_* + _fce_* meta).
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
		$routes = array(
			'set-photo'      => 'fccd_rest_set_photo',
			'stock-search'   => 'fccd_rest_stock_search',
			'stock-import'   => 'fccd_rest_stock_import',
			'save-cta'       => 'fccd_rest_save_cta',
			'breeze-forms'   => 'fccd_rest_breeze_forms',
			'breeze-embed'   => 'fccd_rest_breeze_embed',
			'preview'        => 'fccd_rest_preview',
		);
		$gets = array( 'breeze-forms', 'preview' );
		foreach ( $routes as $path => $cb ) {
			register_rest_route( 'firstchurch/v1', '/comms-desk/' . $path, array(
				'methods'             => in_array( $path, $gets, true ) ? 'GET' : 'POST',
				'permission_callback' => $can,
				'callback'            => $cb,
			) );
		}
	}
);

/** A managed draft we're allowed to edit from the desk. */
function fccd_editable_post( $id ) {
	$id   = (int) $id;
	$post = $id ? get_post( $id ) : null;
	if ( ! $post || ! current_user_can( 'edit_post', $id ) ) {
		return null;
	}
	return $post;
}

/** Set the featured image from a media-library attachment. */
function fccd_rest_set_photo( WP_REST_Request $req ) {
	$p     = (array) $req->get_json_params();
	$post  = fccd_editable_post( $p['draft_id'] ?? 0 );
	$att   = (int) ( $p['attachment_id'] ?? 0 );
	if ( ! $post || ! $att || 'attachment' !== get_post_type( $att ) ) {
		return new WP_REST_Response( array( 'error' => 'Invalid post or attachment.' ), 400 );
	}
	set_post_thumbnail( $post->ID, $att );
	return new WP_REST_Response( array( 'ok' => true, 'thumb' => wp_get_attachment_image_url( $att, 'medium' ) ), 200 );
}

/** Search stock photos (reuses the firstchurch-stock-photos provider layer). */
function fccd_rest_stock_search( WP_REST_Request $req ) {
	$p = (array) $req->get_json_params();
	if ( ! function_exists( 'fcsp_search' ) ) {
		return new WP_REST_Response( array( 'error' => 'Stock photos unavailable.' ), 400 );
	}
	$res = fcsp_search( array( 'query' => (string) ( $p['query'] ?? '' ), 'orientation' => 'wide' ) );
	if ( is_wp_error( $res ) ) {
		return new WP_REST_Response( array( 'error' => $res->get_error_message() ), 400 );
	}
	// Trim each result to what the picker needs.
	$out = array();
	foreach ( (array) ( $res['results'] ?? array() ) as $r ) {
		$out[] = array(
			'thumbnail' => (string) ( $r['thumbnail'] ?? '' ),
			'url'       => (string) ( $r['url'] ?? '' ),
			'title'     => (string) ( $r['title'] ?? '' ),
			'creator'   => (string) ( $r['creator'] ?? '' ),
			'meta'      => $r, // full record echoed back for the import call
		);
	}
	return new WP_REST_Response( array( 'results' => $out ), 200 );
}

/** Import a chosen stock photo and set it as the draft's featured image. */
function fccd_rest_stock_import( WP_REST_Request $req ) {
	$p    = (array) $req->get_json_params();
	$post = fccd_editable_post( $p['draft_id'] ?? 0 );
	$m    = (array) ( $p['meta'] ?? array() );
	if ( ! $post || ! function_exists( 'fcsp_import' ) || empty( $m['url'] ) ) {
		return new WP_REST_Response( array( 'error' => 'Invalid request.' ), 400 );
	}
	$res = fcsp_import( array(
		'image_url'    => (string) $m['url'],
		'title'        => (string) ( $m['title'] ?? '' ),
		'alt'          => (string) ( $m['title'] ?? '' ),
		'post_id'      => $post->ID, // fcsp_import sets it as the featured image
		'openverse_id' => (string) ( $m['id'] ?? '' ),
		'creator'      => (string) ( $m['creator'] ?? '' ),
		'creator_url'  => (string) ( $m['creator_url'] ?? '' ),
		'license'      => (string) ( $m['license'] ?? '' ),
		'license_url'  => (string) ( $m['license_url'] ?? '' ),
		'attribution'  => (string) ( $m['attribution'] ?? '' ),
		'source'       => (string) ( $m['source'] ?? '' ),
		'foreign_url'  => (string) ( $m['foreign_url'] ?? '' ),
		'provider'     => (string) ( $m['provider'] ?? '' ),
	) );
	if ( is_wp_error( $res ) ) {
		return new WP_REST_Response( array( 'error' => $res->get_error_message() ), 400 );
	}
	return new WP_REST_Response( array( 'ok' => true, 'thumb' => $res['attachment_url'] ?? '' ), 200 );
}

/** Save an announcement's call-to-action. */
function fccd_rest_save_cta( WP_REST_Request $req ) {
	$p    = (array) $req->get_json_params();
	$post = fccd_editable_post( $p['draft_id'] ?? 0 );
	if ( ! $post ) {
		return new WP_REST_Response( array( 'error' => 'Invalid post.' ), 400 );
	}
	$text = sanitize_text_field( (string) ( $p['cta_text'] ?? '' ) );
	$url  = esc_url_raw( (string) ( $p['cta_url'] ?? '' ) );
	update_post_meta( $post->ID, 'fcs_cta_text', $text );
	update_post_meta( $post->ID, 'fcs_cta_url', $url );
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/** Live Breeze form catalog (active forms only) — for the no-form suggestion list. */
function fccd_rest_breeze_forms( WP_REST_Request $req ) {
	if ( ! function_exists( 'fcbf_records' ) ) {
		return new WP_REST_Response( array( 'forms' => array() ), 200 );
	}
	$forms = array();
	foreach ( fcbf_records() as $r ) {
		$forms[] = array(
			'id'   => (string) ( $r['id'] ?? '' ),
			'name' => (string) ( $r['name'] ?? '' ),
		);
	}
	return new WP_REST_Response( array( 'forms' => $forms ), 200 );
}

/** Embed a Breeze form on an event (reuses the shared breeze-forms helper). */
function fccd_rest_breeze_embed( WP_REST_Request $req ) {
	$p    = (array) $req->get_json_params();
	$post = fccd_editable_post( $p['draft_id'] ?? 0 );
	$fid  = (string) ( $p['form_id'] ?? '' );
	if ( ! $post || '' === $fid || ! function_exists( 'fcbf_embed_breeze_form' ) ) {
		return new WP_REST_Response( array( 'error' => 'Invalid request.' ), 400 );
	}
	fcbf_embed_breeze_form( $post->ID, $fid );
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Render the draft body exactly as the front end will, so the coordinator can
 * read what publishes (shortcodes like [breeze_form], blocks, wpautop) inline
 * on the Desk instead of opening the editor. Read-only.
 */
function fccd_rest_preview( WP_REST_Request $req ) {
	$post = fccd_editable_post( $req->get_param( 'draft_id' ) );
	if ( ! $post ) {
		return new WP_REST_Response( array( 'error' => 'Draft not found.' ), 404 );
	}
	$html = apply_filters( 'the_content', $post->post_content );
	return new WP_REST_Response( array( 'html' => wp_kses_post( (string) $html ) ), 200 );
}
