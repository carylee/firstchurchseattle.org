<?php
/**
 * Google Fonts delivery tweaks.
 *
 * The Maranatha parent builds its Google Fonts <link> from
 * ctfw_google_fonts_style_url() and enqueues it (handle: maranatha-google-fonts)
 * with no font-display strategy and no connection pre-warming. That causes a
 * flash-of-invisible-text (FOIT) while the font downloads. Two small, low-risk
 * fixes, both via filters so the parent stays untouched:
 *
 *   1. Append &display=swap to the fonts URL  → text paints immediately in a
 *      fallback, then swaps to the web font (no invisible-text gap).
 *   2. preconnect to the Google Fonts hosts   → the TLS handshake to
 *      fonts.googleapis.com / fonts.gstatic.com starts earlier.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append display=swap to the parent's Google Fonts URL.
 *
 * The URL always carries a `?family=` query (see ctfw_google_fonts_style_url),
 * so a `&display=swap` is the correct join. Guarded so we never double-add it.
 *
 * @param string $url Google Fonts stylesheet URL (may be protocol-relative).
 * @return string
 */
add_filter(
	'ctfw_google_fonts_style_url',
	static function ( $url ) {
		if ( ! is_string( $url ) || '' === $url || false !== strpos( $url, 'display=' ) ) {
			return $url;
		}
		return $url . '&display=swap';
	}
);

/**
 * Pre-warm the connection to the Google Fonts hosts.
 *
 * Added on the 'preconnect' relation only. fonts.gstatic.com serves the font
 * files cross-origin and so needs the crossorigin attribute; fonts.googleapis.com
 * serves the CSS.
 *
 * @param array<int,mixed> $hints    URLs/attribute-arrays for this relation.
 * @param string           $relation The resource-hint relation being filtered.
 * @return array<int,mixed>
 */
add_filter(
	'wp_resource_hints',
	static function ( $hints, $relation ) {
		if ( 'preconnect' !== $relation ) {
			return $hints;
		}
		$hints[] = 'https://fonts.googleapis.com';
		$hints[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
		return $hints;
	},
	10,
	2
);
