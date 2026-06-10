<?php
/**
 * Map Section — child theme override.
 *
 * The parent calls this partial from four places (see widget-section.php for
 * the homepage slot right after the first/hero section; also single events,
 * single locations, and the footer). On the homepage we claim that slot:
 * instead of the half-viewport live Google Map — heavyweight, and logistics a
 * first-time visitor doesn't need before deciding to visit — we render the
 * compact visit card + happenings strip (partials/home-visit-happenings.php).
 *
 * Every other surface falls through to the parent's partial unchanged, so
 * single event/location maps keep working. (The footer map instance is
 * separately suppressed by inc/footer-map.php.)
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/homepage.php' )
	&& empty( $GLOBALS['maranatha_top_map_attempted'] ) ) {

	// Mirror the parent's globals so its footer branch never re-attempts a map.
	$GLOBALS['maranatha_top_map_attempted'] = true;
	$GLOBALS['maranatha_top_map_shown']     = true;

	get_template_part( 'partials/home-visit-happenings' );
	return;
}

// Single events, single locations, footer: parent behavior, verbatim.
require get_template_directory() . '/partials/map-section.php';
