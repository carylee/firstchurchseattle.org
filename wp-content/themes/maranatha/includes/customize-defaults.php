<?php
/**
 * Theme Customizer Defaults
 *
 * Define defaults for Customizer and make available to framework for use with framework functions.
 *
 * @package    Maranatha
 * @subpackage Functions
 * @copyright  Copyright (c) 2015, ChurchThemes.com
 * @link       https://churchthemes.com/themes/maranatha
 * @license    GPLv2 or later
 * @since      1.0
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

/**
 * Default Values
 *
 * Make defaults available to framework for use anywhere with ctfw_customize_defaults().
 *
 * Assists in setting defaults when adding settings and with getting defaults for output.
 * These apply only to options array, not theme_mod or anything else.
 *
 * @since 1.0
 * @return array Default values
 */
function maranatha_customize_defaults() {

	// Default values
	$defaults = array(

		/**
		 * Colors
		 */

		'main_color' => array(
			'value'		=> '#c77444', // #cc7c4b
			'no_empty'	=> true
		),

		'link_color' => array(
			'value'		=> '#c77444', // #cf7d41'
			'no_empty'	=> true
		),

		/**
		 * Fonts (Google Fonts)
		 */

		'logo_font' => array(
			'value'		=> 'Raleway',
			'no_empty'	=> true
		),

		'menu_font' => array(
			'value'		=> 'Lato',
			'no_empty'	=> true
		),

		'heading_font' => array(
			'value'		=> 'Raleway',
			'no_empty'	=> true
		),

		'body_font' => array(
			'value'		=> 'Lato',
			'no_empty'	=> true
		),

		'font_subsets' => array(
			'value'		=> '',
			'no_empty'	=> false
		),

		/**
		 * Logo
		 */

		'logo_type' => array(
			'value'		=> 'text',
			'no_empty'	=> true
		),

		'logo_image' => array(
			'value'		=> '',
			'no_empty'	=> false
		),

		'logo_hidpi' => array(
			'value'		=> '',
			'no_empty'	=> false
		),

		'logo_text' => array(
			/* translators: Default value for Logo Text */
			'value'		=> __( 'Church Name', 'maranatha' ),
			'no_empty'	=> true
		),

		'logo_text_size' => array(
			'value'		=> 'large',
			'no_empty'	=> true
		),

		/**
		 * Header Image
		 */

		'header_image_opacity' => array(
			'value'		=> '10', // percent
			'no_empty'	=> true
		),

		/**
		 * Footer Content
		 */

		'show_footer_location' => array(
			'value'		=> true,
			'no_empty'	=> false
		),

		'footer_icon_urls' => array(
			/* translators: This is a default option value for footer icons */
			'value'		=> __( "https://facebook.com\nhttps://twitter.com\nhttps://vimeo.com\nhttp://instagram.com\nhttp://itunes.com", 'maranatha' ),
			'no_empty'	=> false
		),

		'footer_notice' => array(
			/* translators: This is a default option value for footer copyright/notice */
			'value'		=> sprintf(
								__( '&copy; [ctcom_current_year] [ctcom_site_name] - Powered by <a href="%s" target="_blank" rel="nofollow noopener noreferrer">ChurchThemes.com</a>', 'maranatha' ),
								'https://churchthemes.com'
							),
			'no_empty'	=> false
		),

		'bottom_left_sticky' => array(
			'value'		=> 'events',
			'no_empty'	=> true
		),

		'bottom_left_sticky_items_limit' => array(
			'value'		=> '2',
			'no_empty'	=> true
		),

		'bottom_left_sticky_content' => array(
			'value'		=> '',
			'no_empty'	=> false
		),

		/**
		 * Homepage (Static Front Page)
		 */

		'show_home_location' => array(
			'value'		=> true,
			'no_empty'	=> false
		),

	);

	return $defaults;

}

add_filter( 'ctfw_customize_defaults', 'maranatha_customize_defaults' );
