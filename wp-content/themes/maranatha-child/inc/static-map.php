<?php
/**
 * Static map of the church — shared builder + in-content placeholder.
 *
 * One source of truth for the Google Static Maps image of First Church
 * (served from the API per Google's ToS, never Maps JS — the live map was
 * retired in partials/map-section.php). Coordinates come from the first
 * ctc_location post's authored meta, the API key from the Church Content
 * plugin settings. Everything fails soft to '' / no output.
 *
 * Two consumers:
 * - footer.php calls fcs_static_map_image() for the small Contact-column map.
 * - Page content can hold an empty `<div class="fcs-contact-map"></div>`
 *   (kses-safe, so editable via the MCP server); the the_content filter
 *   below replaces it with a larger directions-linked map. Content saved
 *   before this file deploys just renders the empty div — invisible.
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
 * Thin wrapper over the parent framework's ctfw_google_map_image() that
 * supplies the church's coordinates and brand marker color.
 *
 * @param array $args Overrides for ctfw_google_map_image() (zoom/width/height/alt…).
 * @return string The <img> markup, or '' without helpers, coordinates, or an API key.
 */
function fcs_static_map_image( $args = array() ) {

	if ( ! function_exists( 'ctfw_first_ordered_post' )
		|| ! function_exists( 'ctfw_location_data' )
		|| ! function_exists( 'ctfw_google_map_image' )
		|| ! function_exists( 'ctfw_google_maps_api_key' )
		|| ! ctfw_google_maps_api_key() ) {
		return '';
	}

	$location = ctfw_first_ordered_post( 'ctc_location' );

	if ( empty( $location['ID'] ) ) {
		return '';
	}

	$loc_data = ctfw_location_data( $location['ID'] );

	if ( empty( $loc_data['map_lat'] ) || empty( $loc_data['map_lng'] ) ) {
		return '';
	}

	return ctfw_google_map_image( wp_parse_args( $args, array(
		'latitude'     => $loc_data['map_lat'],
		'longitude'    => $loc_data['map_lng'],
		'zoom'         => 14,
		'width'        => 400,
		'height'       => 200,
		'marker_color' => '70334e',
		'alt'          => __( 'Map showing First Church at 180 Denny Way, Seattle', 'maranatha-child' ),
	) ) );
}

/**
 * Replace the in-content map placeholder with a directions-linked static map.
 *
 * Runs after wpautop/do_shortcode (priority 20) and only does work when the
 * placeholder class is present in the content.
 *
 * @param string $content Post content.
 * @return string Content with the placeholder filled (or left empty if no map).
 */
function fcs_fill_content_map_placeholder( $content ) {

	if ( false === strpos( $content, 'fcs-contact-map' ) ) {
		return $content;
	}

	$map = fcs_static_map_image( array(
		'zoom'   => 15,
		'width'  => 640,
		'height' => 400,
	) );

	if ( ! $map ) {
		return $content; // No key/coords — leave the invisible empty div.
	}

	$html = sprintf(
		'<a class="fcs-content-map" href="%s" target="_blank" rel="noopener noreferrer" title="%s">%s</a>',
		esc_url( 'https://www.google.com/maps/dir/?api=1&destination=180+Denny+Way%2C+Seattle%2C+WA+98109' ),
		esc_attr__( 'Get directions to First Church', 'maranatha-child' ),
		$map
	);

	return preg_replace(
		'#<div class="fcs-contact-map">\s*</div>#',
		$html,
		$content
	);
}
add_filter( 'the_content', 'fcs_fill_content_map_placeholder', 20 );
