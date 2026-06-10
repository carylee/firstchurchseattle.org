<?php
/**
 * Map Section — child theme override. The live Google Map is retired.
 *
 * The parent calls this partial from four places: the homepage slot right
 * after the first/hero section (widget-section.php), single events
 * (content-event-full.php), single locations (content-location-full.php),
 * and the footer (footer.php — unused, the child overrides footer.php).
 *
 * - Homepage: the slot renders the compact visit card + happenings strip
 *   instead (partials/home-visit-happenings.php).
 * - Everywhere else: nothing. The half-viewport live map was the only
 *   caller of ctfw_google_map(), so the Google Maps JS no longer loads
 *   anywhere on the site. A small *static* map image lives in the footer
 *   (footer.php) instead.
 *
 * Note this also covers legacy ctc_event singles and /locations/ pages —
 * their body content (address, times, photos) still renders; only the map
 * block above it is gone.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Mirror the parent's globals so no other branch re-attempts a map render.
$GLOBALS['maranatha_top_map_attempted'] = true;
$GLOBALS['maranatha_top_map_shown']     = true;

if ( is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/homepage.php' )
	&& empty( $GLOBALS['fcs_home_visit_rendered'] ) ) {

	// Render once — the parent's footer also reloads this partial.
	$GLOBALS['fcs_home_visit_rendered'] = true;

	get_template_part( 'partials/home-visit-happenings' );
}
