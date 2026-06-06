<?php
/**
 * Sidebar / Widget Area Functions
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

/**********************************
 * REGISTER SIDEBARS
 **********************************/

/**
 * Register sidebars
 *
 * Also see maranatha_sidebar_restrictions() below to restrict which widgets a sidebar allows.
 * Using ctcom- prefix common to all churchthemes.com themes to assist with theme switching.
 *
 * @since 1.0
 */
function maranatha_register_sidebars() {

	// Home Sections
	register_sidebar( array(
		'id'			=> 'ctcom-home-sections',
		'name'			=> _x( 'Homepage Sections', 'widget area', 'maranatha' ),
		'description' 	=> __( 'Add CT Section widgets to show on the homepage.', 'maranatha' ),
		'before_widget'	=> '', // absent instead of empty (JS console error since 4.6 Beta)
		'after_widget'	=> '',
		'before_title' 	=> '',
		'after_title' 	=> '',
	) );

	// Footer
	register_sidebar( array(
		'id'			=> 'ctcom-footer',
		'name'			=> _x( 'Footer', 'sidebar', 'maranatha' ),
		'description' 	=> __( 'Show three widgets in the footer.', 'maranatha' ),
		'before_widget'	=> '<aside id="%1$s" class="maranatha-widget %2$s">',
		'after_widget'	=> '</aside>',
		'before_title' 	=> '<h2 class="maranatha-widget-title">',
		'after_title' 	=> '</h2>',
	) );

}

add_action( 'widgets_init', 'maranatha_register_sidebars' );

/**********************************
 * RESTRICT SIDEBARS/WIDGETS
 **********************************/

/**
 * Sidebar widget restrictions
 *
 * Restrictions based on which widgets a sidebar allows or disallows.
 * A limit can be set for the number of widgets a sidebar will show.
 * The framework receives this data via the maranatha_sidebar_widget_restrictions filter.
 *
 * Also see the converse (Widget sidebar restrictions) - both are necessary in consideration
 * of widgets and sidebars added by plugins or child theming
 *
 * Must use add_theme_support( 'ctfw_sidebar_widget_restrictions' ).
 *
 * @since 1.0
 * @param array $restrictions
 * @return array Restrictions configuration
 */
function maranatha_sidebar_widget_restrictions( $restrictions ) {

	$restrictions = array(

		// Home Sections
		'ctcom-home-sections' => array( // use this ID for themes having only CT Section support
			'include_widgets' 	=> array( 'ctfw-section' ), // allow only these widgets (empty to allow all)
			'exclude_widgets' 	=> array(), // never allow these widgets
			'limit' 			=> false, // do not show more than this on front-end
		),

		// Footer
		'ctcom-footer' => array( // use this ID for any theme having footer widgets
			'include_widgets' 	=> array(), // allow only these widgets (empty to allow all)
			'exclude_widgets' 	=> array( 'ctfw-section' ), // never allow these widgets
			'limit' 			=> 3, // do not show more than this on front-end
		),

	);

	return $restrictions;

}

add_filter( 'ctfw_sidebar_widget_restrictions', 'maranatha_sidebar_widget_restrictions' );

/**
 * Widget sidebar restrictions
 *
 * Restrictions based on which sidebars a widget allows or disallows itself to be used in.
 * The framework receives this data via the maranatha_widget_sidebar_restrictions filter.
 *
 * Also see the converse (Sidebar widget restrictions) - both are necessary in consideration
 * of widgets and sidebars added by plugins or child theming.
 *
 * @since 1.0
 * @param array $restrictions
 * @return array Restrictions configuration
 */
function maranatha_widget_sidebar_restrictions( $restrictions ) {

	$restrictions = array(

		// Categories Widget
		'ctfw-categories' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// Posts Widget
		'ctfw-posts' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// Sermons Widget
		'ctfw-sermons' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// Events Widget
		'ctfw-events' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// Gallery Widget
		'ctfw-gallery' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// People Widget
		'ctfw-people' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// Locations Widget
		'ctfw-locations' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// Archives Widget
		'ctfw-archives' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// Giving Widget
		'ctfw-giving' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// Highlight Widget
		'ctfw-highlight' => array(
			'include_sidebars' => array(), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array( 'ctcom-home-sections' ), // never allow in these sidebars
		),

		// Section Widget
		'ctfw-section' => array(
			'include_sidebars' => array( 'ctcom-home-sections' ), // allow in only these sidebars (empty to allow in all)
			'exclude_sidebars' => array(), // never allow in these sidebars
		),

	);

	return $restrictions;

}

add_filter( 'ctfw_widget_sidebar_restrictions', 'maranatha_widget_sidebar_restrictions' );
