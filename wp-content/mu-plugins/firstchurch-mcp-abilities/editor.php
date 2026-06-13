<?php
/**
 * Block-editor "Rewrite in church voice" — a Rich Text toolbar button backed by
 * a thin REST route that calls the firstchurch/rewrite-in-voice ability (which
 * runs on the core AI Client with fc_church_voice()).
 *
 * We expose a small custom REST route rather than the core JS AI client: the
 * core team flags that client as still-evaluating, and a route gives us a clean
 * capability + nonce gate. Same church voice as intake — one source of truth.
 *
 * Loaded by ../firstchurch-mcp-abilities.php.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'firstchurch/v1',
			'/voice/rewrite',
			array(
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => static function ( WP_REST_Request $req ) {
					$p    = $req->get_json_params();
					$p    = is_array( $p ) ? $p : array();
					$text = (string) ( $p['text'] ?? '' );
					if ( '' === trim( $text ) ) {
						return new WP_REST_Response( array( 'error' => 'No text provided.', 'code' => 'missing_text' ), 400 );
					}
					$r = fcmcp_rewrite_in_voice( array( 'text' => $text, 'kind' => (string) ( $p['kind'] ?? 'selection' ) ) );
					if ( is_wp_error( $r ) ) {
						return new WP_REST_Response( array( 'error' => $r->get_error_message(), 'code' => $r->get_error_code() ), 400 );
					}
					return new WP_REST_Response( $r, 200 ); // { text: "…" }
				},
			)
		);
	}
);

add_action(
	'enqueue_block_editor_assets',
	static function (): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$path = __DIR__ . '/assets/voice-editor.js';
		if ( ! is_readable( $path ) ) {
			return;
		}
		wp_enqueue_script(
			'firstchurch-voice-editor',
			content_url( 'mu-plugins/firstchurch-mcp-abilities/assets/voice-editor.js' ),
			array( 'wp-rich-text', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
			(string) filemtime( $path ),
			true
		);
	}
);
