<?php
/**
 * REST receiver for the bulletin Publish action:
 *
 *   POST /wp-json/firstchurch/v1/publish-bulletin
 *   header: X-Publish-Secret: <shared secret>
 *   body:   { "date": "YYYY-MM-DD", "html": "<…>", "pdf_base64": "<…>" }
 *
 * Writes bulletin/<date>.html and bulletin/<date>.pdf into the web-root /bulletin/
 * dir (FCBP_DIR), which bulletin/index.php serves. This is CONTENT (mirrored, not
 * git-tracked per ops/sync/ownership.md), so it's written live on prod here — not
 * shipped through the code deploy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', static function () {
	register_rest_route( 'firstchurch/v1', '/publish-bulletin', array(
		'methods'             => 'POST',
		'callback'            => 'fcbp_rest_publish',
		'permission_callback' => 'fcbp_authorize',
		'args'                => array(
			'date' => array(
				'type'     => 'string',
				'required' => true,
				// Anchored YYYY-MM-DD: also the path-traversal guard — the date
				// becomes the filename, so nothing but digits and dashes lands.
				'pattern'  => '^\d{4}-\d{2}-\d{2}$',
			),
			'html'       => array( 'type' => 'string', 'required' => true ),
			'pdf_base64' => array( 'type' => 'string', 'required' => true ),
		),
	) );
} );

// The secret the Worker must present, from a wp-config constant (preferred) or an
// option. Empty when neither is set → the route fails closed.
function fcbp_expected_secret(): string {
	if ( defined( 'FC_BULLETIN_PUBLISH_SECRET' ) && FC_BULLETIN_PUBLISH_SECRET ) {
		return (string) FC_BULLETIN_PUBLISH_SECRET;
	}
	return (string) get_option( FCBP_OPTION_SECRET, '' );
}

function fcbp_authorize( WP_REST_Request $req ): bool {
	$expected = fcbp_expected_secret();
	if ( '' === $expected ) {
		return false; // not configured → deny
	}
	$provided = (string) $req->get_header( 'x-publish-secret' );
	return '' !== $provided && hash_equals( $expected, $provided );
}

function fcbp_rest_publish( WP_REST_Request $req ) {
	$date = (string) $req->get_param( 'date' );
	// Belt-and-suspenders: the route `pattern` already enforces this, but re-check
	// before using $date in a path.
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return new WP_Error( 'fcbp_bad_date', 'date must be YYYY-MM-DD', array( 'status' => 400 ) );
	}

	$html = (string) $req->get_param( 'html' );
	$pdf  = base64_decode( (string) $req->get_param( 'pdf_base64' ), true );
	if ( false === $pdf || '' === $pdf ) {
		return new WP_Error( 'fcbp_bad_pdf', 'pdf_base64 is not valid base64', array( 'status' => 400 ) );
	}
	if ( substr( $pdf, 0, 5 ) !== '%PDF-' ) {
		return new WP_Error( 'fcbp_not_pdf', 'decoded pdf is not a PDF', array( 'status' => 400 ) );
	}
	if ( '' === trim( $html ) ) {
		return new WP_Error( 'fcbp_empty_html', 'html is empty', array( 'status' => 400 ) );
	}

	if ( ! is_dir( FCBP_DIR ) && ! wp_mkdir_p( FCBP_DIR ) ) {
		return new WP_Error( 'fcbp_no_dir', 'bulletin directory is not writable', array( 'status' => 500 ) );
	}

	$html_path = FCBP_DIR . $date . '.html';
	$pdf_path  = FCBP_DIR . $date . '.pdf';
	if ( false === file_put_contents( $html_path, $html ) || false === file_put_contents( $pdf_path, $pdf ) ) {
		return new WP_Error( 'fcbp_write_failed', 'could not write bulletin files', array( 'status' => 500 ) );
	}

	$base = home_url( '/bulletin/' );
	return new WP_REST_Response( array(
		'ok'       => true,
		'date'     => $date,
		'html_url' => $base . '?date=' . $date,
		'pdf_url'  => $base . '?date=' . $date . '&format=pdf',
		'bytes'    => array( 'html' => strlen( $html ), 'pdf' => strlen( $pdf ) ),
	) );
}
