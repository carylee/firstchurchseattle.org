<?php
/**
 * Enqueue Stylesheets
 *
 * @package    Maranatha
 * @subpackage Functions
 * @copyright  Copyright (c) 2015, ChurchThemes.com
 * @link       https://churchthemes.com/themes/maranatha
 * @license    GPLv2 or later
 * @since      1.0
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enqueue stylesheets
 *
 * @since 1.0
 */
function maranatha_enqueue_styles() {

	// Google Fonts
	$fonts = array(
		ctfw_customization( 'logo_font' ),
		ctfw_customization( 'heading_font' ),
		ctfw_customization( 'menu_font' ),
		ctfw_customization( 'body_font' )
	);
	$google_fonts_url = ctfw_google_fonts_style_url( $fonts, ctfw_customization( 'font_subsets' ) );
	if ( $google_fonts_url ) {
		wp_enqueue_style( 'maranatha-google-fonts', $google_fonts_url, false, null ); // null - don't mess with Google Fonts URL by adding version
	}

	// Elusive Icon Font - http://aristeides.com/elusive-iconfont/
	// (before main so can override styles when necessary)
	wp_enqueue_style( 'elusive-icons', get_theme_file_uri( CTFW_THEME_CSS_DIR . '/lib/elusive-icons.min.css' ), false, CTFW_THEME_VERSION );  // bust cache on theme update

	// Main Stylesheet
	wp_enqueue_style( 'maranatha-style', get_stylesheet_uri(), false, CTFW_THEME_VERSION );  // bust cache on theme update

	// Tooltipster base styles
	// style.css and color stylesheets contain the .maranatha-tooltipster theme
	// Event calendar template and single event (recurrence tooltip)
	if (
		is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/events-calendar.php' )
		|| is_singular( 'ctc_event' )
	) {
		wp_enqueue_style( 'tooltipster', get_theme_file_uri( CTFW_THEME_CSS_DIR . '/lib/tooltipster.css' ), false, CTFW_THEME_VERSION );  // bust cache on theme update
	}

}

add_action( 'wp_enqueue_scripts', 'maranatha_enqueue_styles' ); // front-end only (and yes, wp_enqueue_scripts is correct for styles)
