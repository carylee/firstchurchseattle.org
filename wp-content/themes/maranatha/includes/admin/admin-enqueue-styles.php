<?php
/**
 * Theme Admin Styles
 *
 * Also see framework/includes/admin/enqueue-admin-styles.php
 *
 * @package    Maranatha
 * @subpackage Admin
 * @copyright  Copyright (c) 2016, ChurchThemes.com
 * @link       https://churchthemes.com/themes/maranatha
 * @license    GPLv2 or later
 * @since      1.4
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/*******************************************
 * ENQUEUE STYLESHEETS
 *******************************************/

/**
 * Enqueue admin stylesheets
 *
 * @since 1.4
 */
function maranatha_admin_enqueue_styles() {

	$screen = get_current_screen();

	// Admin widgets
	if ( 'widgets' == $screen->base ) {

		// CSS for admin widgets
		// Theme also enqueues this for Customizer in includes/customize.php
		wp_enqueue_style( 'maranatha-admin-widgets', get_theme_file_uri( CTFW_THEME_CSS_DIR . '/admin/admin-widgets.css' ), false, CTFW_THEME_VERSION ); // bust cache on update

	}


}

add_action( 'admin_enqueue_scripts', 'maranatha_admin_enqueue_styles' );
