<?php
/**
 * Image Functions
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

/***********************************************
 * IMAGE SIZES
 ***********************************************/

/**
 * Add image sizes
 *
 * @since 1.0
 */
function maranatha_image_sizes() {

	/*********************************
	 * THUMBNAILS
	 *********************************/

	// Default Thumbnail (post-thumbnail)
	// Shown on short entries and in prev/next post nav
	set_post_thumbnail_size( 800, 200, true ); // crop for exact size

	// Small Thumbnail
	// Used in person feature image (shown at half size for HiDPI)
	add_image_size( 'maranatha-thumb-small', 240, 240, true ); // crop for exact size

	/*********************************
	 * HOMEPAGE IMAGES
	 *********************************/

	// Section Image (Homepage Widget)
	// This size is excluded from upscaling in support-framework.php so not ever image uploaded is upscaled to this size
	add_image_size( 'maranatha-section', 1680, 1050, true ); // crop for exact size

	/*********************************
	 * HEADER IMAGES
	 *********************************/

	// Banner Image
	// Featured image to appear at the top of pages
	// This size is excluded from upscaling in support-framework.php so not ever image uploaded is upscaled to this size
	add_image_size( 'maranatha-banner', 1600, 400, true ); // crop for exact size

	/*********************************
	 * RECTANGULAR IMAGES
	 *********************************/

	// Large Thumbnail (Highlight Widget, Gallery 1 - 2 Columns, Gallery Widget Large)
	// Just wide enough for one widget per row while responsive
	add_image_size( 'maranatha-rect-large', 768, 512, true ); // crop for exact size

	// Medium Thumbnail (Gallery 3 - 5 Columns, Gallery Widget Medium)
	add_image_size( 'maranatha-rect-medium', 480, 320, true ); // crop for exact size

	// Small Thumbnail (Gallery 6 - 9 Columns, Gallery Widget Small)
	add_image_size( 'maranatha-rect-small', 240, 160, true ); // crop for exact size

}

add_action( 'after_setup_theme', 'maranatha_image_sizes', 9 ); // before maranatha_add_theme_support_framework() so it can use ctfw_image_size_dimensions()

/**
 * Set content width
 *
 * This affect maximum embed and image sizes.
 * On front end CSS handles most of this but content editor also uses.
 *
 * Keep an eye on this for possible future add_theme_support() implementation:
 * http://core.trac.wordpress.org/ticket/21256
 *
 * @since 1.0
 * @global int $content_width
 */
function maranatha_set_content_width() {

	global $content_width;

	if ( ! isset( $content_width ) ) {

		// Width depends on page template, archive, singular, etc.
		$content_width = maranatha_content_width();

		// No sidebar in Maranatha

	}

}

add_action( 'wp', 'maranatha_set_content_width' );

/**
 * Logo image size
 *
 * This data is used in Customizer to make a recommendation to the user
 * and is used in header-logo.php for outputting logo markup.
 *
 * These values are duplicated in _variables.scss (update both files if change).
 *
 * @since 1.0
 * @param string $key If key provided, that value is returned; otherwise whole array
 * @return string|array Value for one key or whole array if none
 */
function maranatha_logo_size( $key = false ) {

	$logo_size_data = array();

	$logo_size_data['max_width'] = 300;
	$logo_size_data['max_height'] = 50;
	$logo_size_data['max_height_small'] = 30; // on sticky scrolling and mobile
	$logo_size_data['max_dimensions'] = $logo_size_data['max_width'] . 'x' . $logo_size_data['max_height'];

	$logo_size_data = apply_filters( 'maranatha_logo_size_data', $logo_size_data, $key );

	if ( $key && isset( $logo_size_data[$key] ) ) {
		$value = $logo_size_data[$key];
	} else {
		$value = $logo_size_data;
	}

	$value = apply_filters( 'maranatha_logo_size', $value, $logo_size_data, $key );

	return $value;

}

