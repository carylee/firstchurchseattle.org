<?php
/**
 * Front-end attribution rendering.
 *
 * We record provider/creator/license on every import (the _fcsp_* meta). This
 * surfaces it publicly so attribution obligations are actually met — Openverse
 * serves CC-BY images that legally require credit, and Pexels/Unsplash ask for
 * it by guideline. Three ways to render, from the same data:
 *
 *   - fcsp_attachment_credit_html( $id ): a linked, escaped credit line;
 *   - [stock_credit id="123"] shortcode (defaults to the post's featured image);
 *   - an OPT-IN auto-append under singular featured images, enabled by returning
 *     true from the `fcsp_auto_credit` filter (off by default — turning it on is
 *     a deliberate, site-wide visual change).
 *
 * Markup carries an `.fcsp-credit` class for theming; no stylesheet is enqueued.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Linked, escaped credit HTML for an attachment, or '' if it carries no
 * provenance (i.e. wasn't imported through this plugin).
 */
function fcsp_attachment_credit_html( int $attachment_id ): string {
	$creator     = (string) get_post_meta( $attachment_id, FCSP_META_CREATOR, true );
	$creator_url = (string) get_post_meta( $attachment_id, FCSP_META_CREATOR_URL, true );
	$source      = (string) get_post_meta( $attachment_id, FCSP_META_SOURCE, true );
	$provider    = (string) get_post_meta( $attachment_id, FCSP_META_PROVIDER, true );
	$foreign     = (string) get_post_meta( $attachment_id, FCSP_META_FOREIGN_URL, true );
	$license     = (string) get_post_meta( $attachment_id, FCSP_META_LICENSE, true );
	$license_url = (string) get_post_meta( $attachment_id, FCSP_META_LICENSE_URL, true );

	// The "source site" to link to: Openverse stores the real origin (flickr,
	// wikimedia); Pexels/Unsplash store themselves.
	$site = '' !== $source ? $source : $provider;
	if ( '' === $creator && '' === $site ) {
		return '';
	}

	$link = static function ( string $text, string $url ): string {
		$text = esc_html( $text );
		if ( '' === $url ) {
			return $text;
		}
		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer nofollow">' . $text . '</a>';
	};

	$site_label = '' !== $site ? ucwords( str_replace( array( '-', '_' ), ' ', $site ) ) : '';

	if ( '' !== $creator ) {
		$html = 'Photo by ' . $link( $creator, '' !== $creator_url ? $creator_url : $foreign );
		if ( '' !== $site_label ) {
			$html .= ' on ' . $link( $site_label, $foreign );
		}
	} else {
		$html = 'Image via ' . $link( $site_label, $foreign );
	}

	if ( '' !== $license ) {
		// Uppercase short CC codes (by-sa, cc0); leave prose licenses ("Pexels
		// License") as-is.
		$license_label = preg_match( '/^[a-z0-9.\-]+$/', $license ) ? strtoupper( $license ) : $license;
		$html         .= ' · ' . $link( $license_label, $license_url );
	}

	return $html;
}

/**
 * [stock_credit id="123"] — render a credit line. With no id, falls back to the
 * current post's featured image.
 */
function fcsp_stock_credit_shortcode( $atts ): string {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'stock_credit' );
	$id   = (int) $atts['id'];
	if ( $id <= 0 ) {
		$id = (int) get_post_thumbnail_id();
	}
	if ( $id <= 0 ) {
		return '';
	}
	$html = fcsp_attachment_credit_html( $id );
	return '' !== $html ? '<span class="fcsp-credit">' . $html . '</span>' : '';
}
add_shortcode( 'stock_credit', 'fcsp_stock_credit_shortcode' );

/**
 * "Stock Photo Credit" block: a dynamic block that renders fcsp_attachment_credit_html
 * for a chosen attachment (or the post's featured image when none is chosen).
 */
function fcsp_register_credit_block(): void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	if ( is_admin() ) {
		wp_register_script(
			'firstchurch-stock-credit-block',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			FCSP_VERSION,
			true
		);
	}
	register_block_type(
		'firstchurch/stock-credit',
		array(
			'api_version'     => 3,
			'editor_script'   => 'firstchurch-stock-credit-block',
			'render_callback' => 'fcsp_render_credit_block',
			'attributes'      => array(
				'id' => array( 'type' => 'number', 'default' => 0 ),
			),
		)
	);
}
add_action( 'init', 'fcsp_register_credit_block' );

/**
 * Render callback for the stock-credit block. Falls back to the post's featured
 * image when no id is set; renders nothing when there's no credit to show.
 */
function fcsp_render_credit_block( $attributes ): string {
	$id = isset( $attributes['id'] ) ? (int) $attributes['id'] : 0;
	if ( $id <= 0 ) {
		$id = (int) get_post_thumbnail_id();
	}
	if ( $id <= 0 ) {
		return '';
	}
	$html = fcsp_attachment_credit_html( $id );
	if ( '' === $html ) {
		return '';
	}
	$wrapper = function_exists( 'get_block_wrapper_attributes' )
		? get_block_wrapper_attributes( array( 'class' => 'fcsp-credit' ) )
		: 'class="fcsp-credit"';
	return '<p ' . $wrapper . '>' . $html . '</p>';
}

/**
 * Opt-in: append the featured image's credit to singular content. Off unless
 * `fcsp_auto_credit` returns true; only fires on the main singular query and
 * only when the featured image actually carries provenance.
 */
function fcsp_maybe_append_featured_credit( $content ) {
	if ( ! apply_filters( 'fcsp_auto_credit', false ) ) {
		return $content;
	}
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$id = (int) get_post_thumbnail_id();
	if ( $id <= 0 ) {
		return $content;
	}
	$html = fcsp_attachment_credit_html( $id );
	if ( '' === $html ) {
		return $content;
	}
	return $content . '<p class="fcsp-credit fcsp-credit--featured">' . $html . '</p>';
}
add_filter( 'the_content', 'fcsp_maybe_append_featured_credit' );
