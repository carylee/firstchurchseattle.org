<?php
/**
 * Theme setup: supports, menus, image handling.
 *
 * (The parent theme used to register these; the theme is standalone now.)
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support(
			'html5',
			array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
		);

		register_nav_menus(
			array(
				'header' => __( 'Header', 'firstchurch' ),
			)
		);

		// The classic editor/content images ceiling. Templates that need the
		// full container width handle it themselves.
		$GLOBALS['content_width'] = 980;
	}
);
