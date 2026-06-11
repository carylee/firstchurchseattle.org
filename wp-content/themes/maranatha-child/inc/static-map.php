<?php
/**
 * Static map of the church — shared builder + in-content placeholder.
 *
 * One source of truth for the static map of First Church. The map is a
 * committed theme asset (assets/map.webp) rendered once from OpenStreetMap
 * tiles by ops/scripts/render-osm-map.py — no Google Maps, no API key, no
 * runtime third-party requests, and it works in local dev. (The live Google
 * map was retired in partials/map-section.php; the Static Maps API version
 * of this helper is gone too.) Attribution is baked into the image per the
 * OSM license.
 *
 * Two consumers:
 * - footer.php calls fcs_static_map_image() for the small Contact-column map
 *   (scaled down by .fcs-footer__map CSS).
 * - Page content can hold an empty `<div class="fcs-contact-map"></div>`
 *   (kses-safe, so editable via the MCP server); the the_content filter
 *   below replaces it with a directions-linked map. Content saved before
 *   this file deploys just renders the empty div — invisible.
 *
 * Styles: the `.fcs-content-map` section of assets/mobile.css.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the static map <img> for the church location.
 *
 * The asset is 1280x800 (a 2x rendering of 640x400 CSS px at zoom 15); the
 * width/height attributes declare the 1x display size so the browser
 * reserves the right box before the image loads.
 *
 * @param array $args Optional overrides: 'alt'.
 * @return string The <img> markup.
 */
function fcs_static_map_image( $args = array() ) {

	$args = wp_parse_args( $args, array(
		'alt' => __( 'Map showing First Church at 180 Denny Way, Seattle', 'maranatha-child' ),
	) );

	return sprintf(
		'<img src="%s" width="640" height="400" alt="%s" decoding="async" />',
		esc_url( get_stylesheet_directory_uri() . '/assets/map.webp?ver=' . fcs_asset_version( 'assets/map.webp' ) ),
		esc_attr( $args['alt'] )
	);
}

/**
 * Replace the in-content map placeholder with a directions-linked static map.
 *
 * Runs after wpautop/do_shortcode (priority 20) and only does work when the
 * placeholder class is present in the content.
 *
 * @param string $content Post content.
 * @return string Content with the placeholder filled.
 */
function fcs_fill_content_map_placeholder( $content ) {

	if ( false === strpos( $content, 'fcs-contact-map' ) ) {
		return $content;
	}

	$html = sprintf(
		'<a class="fcs-content-map" href="%s" target="_blank" rel="noopener noreferrer" title="%s">%s</a>',
		esc_url( 'https://www.google.com/maps/dir/?api=1&destination=180+Denny+Way%2C+Seattle%2C+WA+98109' ),
		esc_attr__( 'Get directions to First Church', 'maranatha-child' ),
		fcs_static_map_image()
	);

	return preg_replace(
		'#<div class="fcs-contact-map">\s*</div>#',
		$html,
		$content
	);
}
add_filter( 'the_content', 'fcs_fill_content_map_placeholder', 20 );
