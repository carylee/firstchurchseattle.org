<?php
/**
 * Drop the redundant footer location map.
 *
 * The parent theme renders a full-height Google map plus an address/contact
 * block in the footer of every non-homepage view (see
 * themes/maranatha/partials/map-section.php — the "Footer?" branch, gated on
 * the `show_footer_location` customization). It repeats on every page, eats a
 * large vertical block, and duplicates the address already shown on the
 * homepage map and on the dedicated /locations/ page.
 *
 * We suppress only the footer instance by filtering the customization value the
 * partial checks. With it falsey, map-section.php never sets `$placement` and
 * returns before emitting the map — so the Google Maps JS for that instance is
 * not loaded either. The homepage map (`show_home_location`) and single
 * event/location maps use different branches and are unaffected. footer.php
 * gates its own `maranatha-footer-has-map` class on the same call, so the
 * footer markup stays self-consistent.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'ctfw_customization',
	function ( $value, $option ) {
		if ( 'show_footer_location' === $option && ! is_admin() ) {
			return '';
		}
		return $value;
	},
	10,
	2
);
