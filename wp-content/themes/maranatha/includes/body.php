<?php
/**
 * <body> Functions
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

/*******************************************
 * BODY CLASSES
 *******************************************/

/**
 * Add helper classes to <body>
 *
 * IMPORTANT: Do not do client detection (mobile, browser, etc.) here.
 * Instead, do in theme's JS so works with caching plugins.
 *
 * Note: Other body classes are added via main.js since their presence
 * is easier to detect in that manner.
 *
 * @since 1.0
 * @param array $classes Classes currently being added to body tag
 * @return array Modified classes
 */
function maranatha_add_body_classes( $classes ) {

	// Fonts
	$fonts_areas = array( 'logo_font', 'heading_font', 'menu_font', 'body_font' );
	foreach ( $fonts_areas as $font_area ) {

		$font_name = ctfw_customization( $font_area );
		$font_name = sanitize_title( $font_name );

		$font_area = str_replace( '_', '-', $font_area );

		$classes[] = 'maranatha-' . $font_area . '-' . $font_name;

	}

	// Logo
	if ( 'image' == ctfw_customization( 'logo_type' ) && ctfw_customization( 'logo_image' ) ) {
		$classes[] = 'maranatha-has-logo-image';
	} else {
		$classes[] = 'maranatha-no-logo-image';
	}

	// Content width
	$classes[] = 'maranatha-content-width-' . maranatha_content_width(); // 700, 980, 1170

	// WordPress 4.8 or earlier (used for MediaElement.js back-compat styling)
	if ( version_compare( $GLOBALS['wp_version'], '4.8', '<=' ) ) {
		$classes[] = 'maranatha-wp-4-8-or-less';
	}

	return $classes;

}

add_filter( 'body_class', 'maranatha_add_body_classes' );
