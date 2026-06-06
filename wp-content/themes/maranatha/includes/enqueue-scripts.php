<?php
/**
 * Enqueue JavaScript
 *
 * @package    Maranatha
 * @subpackage Functions
 * @copyright  Copyright (c) 2015 - 2016, ChurchThemes.com
 * @link       https://churchthemes.com/themes/maranatha
 * @license    GPLv2 or later
 * @since      1.0
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

/**
 * Enqueue JavaScript
 *
 * @since 1.0
 */
function maranatha_enqueue_scripts() {

	// jQuery (included with WordPress)
	wp_enqueue_script( 'jquery' );

	// Viewport Buggyfill
	wp_enqueue_script( 'viewport-units-buggyfill-hacks', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/viewport-units-buggyfill.hacks.js' ), '', CTFW_THEME_VERSION ); // bust cache on theme update
	wp_enqueue_script( 'viewport-units-buggyfill', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/viewport-units-buggyfill.js' ), '', CTFW_THEME_VERSION ); // bust cache on theme update

	// Superfish Menu
	wp_enqueue_script( 'hoverIntent' ); // packaged with WordPress
	wp_enqueue_script( 'superfish', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/superfish.modified.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update
	wp_enqueue_script( 'supersubs', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/supersubs.js' ), array( 'jquery', 'superfish' ), CTFW_THEME_VERSION ); // bust cache on theme update

	// MeanMenu (responsive)
	wp_enqueue_script( 'jquery-meanmenu', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.meanmenu.modified.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update

	// Vide enqueued only when Homepage Section widget has video
	// See widget-templates/homepage-section.php

	// Single Post
	if (is_singular()) { // single post or page

		// comment-reply.js to cause comment form to show below a comment when replying to a comment
		wp_enqueue_script( 'comment-reply' );

		// Comment Validation with jQuery Plugin
		if (comments_open()) { // only if need it for comments form
			wp_enqueue_script( 'jquery-validate', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.validate.min.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update
		}

		// Smooth Scroll - comment and video/audio scroll down
		wp_enqueue_script( 'jquery-smooth-scroll', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.smooth-scroll.min.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update

	}

	// Events Calendar
	if (is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/events-calendar.php' )) {

		// jQuery Visible
		// https://github.com/customd/jquery-visible
		wp_enqueue_script( 'jquery-visible', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.visible.min.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update

		// PJAX
		// https://github.com/defunkt/jquery-pjax
		wp_enqueue_script( 'jquery-pjax', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.pjax.min.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update

	}

	// Tooltipster base styles
	// http://iamceege.github.io/tooltipster/
	// Homepage, event calendar template and single event (recurrence tooltip)
	if (
		is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/homepage.php' ) // scroll arrow
		|| is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/events-calendar.php' ) // hover details
		|| is_singular( 'ctc_event' ) // recurrence note
	) {
		wp_enqueue_script( 'jquery-tooltipster', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.tooltipster.min.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update
	}

	// jQuery Dropdown
	// https://github.com/claviska/jquery-dropdown
	// Used for Archive Dropdowns in header
	if (
        in_array( ctfw_current_content_type(), array(
			'sermon',
			'event',
			'people',
			'blog',
		) )
	) {
		wp_enqueue_script( 'jquery-dropdown-maranatha', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.dropdown.maranatha.min.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update
	}

	// jQuery matchHeight
	// For making people template and group archive rows even
	// Helps alignment when some have "View Profile" button and others don't
	// Also helps with gallery image rows and gaps
	// Note: Load always since gallery widget can be anywhere
	//if ( is_archive( 'ctc_person' ) || is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/people.php' ) ) {
	wp_enqueue_script( 'jquery-matchHeight', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.matchHeight-min.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update

	// Main JS
	wp_enqueue_script( 'maranatha-main', get_theme_file_uri( CTFW_THEME_JS_DIR . '/main.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update

	// Theme data for JavaScript
	wp_localize_script( 'maranatha-main', 'maranatha_main', array( // pass WP data into JS from this point on
		'site_path'							=> ctfw_site_path(),
		'home_url'							=> home_url(),
		'theme_url'							=> CTFW_THEME_URL,
		'is_ssl'							=> is_ssl(),
		'mobile_menu_close'					=> maranatha_icon_class( 'mobile-menu-close', true ),
		'comment_name_required'				=> get_option('require_name_email'), // name and email required on comments? (WP Admin: Settings > Discussion)
		'comment_email_required'			=> get_option('require_name_email'),
		'comment_name_error_required'		=> __( 'Required', 'maranatha' ), // translatable string for comment form validation
		'comment_email_error_required'		=> __( 'Required', 'maranatha' ),
		'comment_email_error_invalid'		=> __( 'Invalid Email', 'maranatha' ),
		'comment_url_error_invalid'			=> __( 'Invalid URL', 'maranatha' ),
		'comment_message_error_required'	=> __( 'Comment Required', 'maranatha' ),
	));

}

add_action( 'wp_enqueue_scripts', 'maranatha_enqueue_scripts' ); // front-end only
