<?php
/**
 * Cross-surface asset loading — what makes the picker available *wherever you
 * insert media*, the way Instant Images is.
 *
 * The trick isn't one integration per surface: nearly every media-insertion UI
 * (classic "Add Media", the featured-image metabox, galleries, and the core
 * Gutenberg image/cover blocks) opens the same shared `wp.media` modal. Add one
 * router/menu tab to that frame and it shows up in all of them. So this file
 * just (a) decides which admin screens can open that modal and (b) enqueues the
 * shared core + the wp.media tab there. The standalone Media ▸ Stock Photos
 * page keeps its own enqueue in admin.php.
 *
 * This file loads unconditionally (not behind is_admin()), so its pure
 * decision functions are unit-testable; the enqueue hooks only do work in admin.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen bases (WP_Screen->base) that can open the wp.media modal and so
 * should carry the in-editor picker. `post` covers both the classic and block
 * post editors (and the featured-image metabox); `upload` covers the media
 * library. Filterable for sites that surface media elsewhere.
 *
 * @return string[]
 */
function fcsp_picker_editor_screens(): array {
	return (array) apply_filters( 'fcsp_picker_editor_screens', array( 'post', 'upload' ) );
}

/**
 * Whether the in-editor picker assets should load on a given admin screen base.
 */
function fcsp_should_load_picker_on_screen( ?string $screen_base ): bool {
	$base = is_string( $screen_base ) ? $screen_base : '';
	if ( '' === $base ) {
		return false;
	}
	return in_array( $base, fcsp_picker_editor_screens(), true );
}

/**
 * The localized data every picker surface shares: REST endpoints, a nonce, and
 * the strings the JS renders. Centralized so the admin page and the modal tab
 * can't drift.
 *
 * @return array<string,mixed>
 */
function fcsp_picker_l10n(): array {
	return array(
		'searchUrl' => esc_url_raw( rest_url( 'firstchurch/v1/stock-photos/search' ) ),
		'importUrl' => esc_url_raw( rest_url( 'firstchurch/v1/stock-photos/import' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'mediaUrl'  => esc_url_raw( admin_url( 'upload.php' ) ),
		'tabTitle'  => __( 'Stock Photos', 'default' ),
		'i18n'      => array(
			'search'    => __( 'Search free, openly-licensed photos…', 'default' ),
			'searchBtn' => __( 'Search', 'default' ),
			'searching' => __( 'Searching…', 'default' ),
			'use'       => __( 'Use this photo', 'default' ),
			'adding'    => __( 'Adding…', 'default' ),
			'noResults' => __( 'No photos found.', 'default' ),
			'failed'    => __( 'Something went wrong. Try again.', 'default' ),
		),
	);
}

/**
 * Register (and localize) the shared search/import core exactly once. Both the
 * standalone page and the modal tab depend on the `firstchurch-stock-core`
 * handle; calling this from either enqueue path is idempotent.
 */
function fcsp_register_picker_core(): void {
	if ( wp_script_is( 'firstchurch-stock-core', 'registered' ) ) {
		return;
	}
	$base = plugin_dir_url( dirname( __FILE__ ) );
	wp_register_script( 'firstchurch-stock-core', $base . 'assets/picker-core.js', array(), FCSP_VERSION, true );
	wp_register_style( 'firstchurch-stock-photos', $base . 'assets/admin.css', array(), FCSP_VERSION );
	wp_localize_script( 'firstchurch-stock-core', 'fcspData', fcsp_picker_l10n() );
}

/**
 * Load the wp.media modal tab on every screen that can open the media modal,
 * so a chosen stock photo can be sideloaded and selected without leaving the
 * editor — featured image, classic Add Media, galleries, and the core image
 * block all ride on the one tab.
 */
add_action(
	'admin_enqueue_scripts',
	static function (): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! fcsp_should_load_picker_on_screen( $screen->base ) ) {
			return;
		}
		if ( ! current_user_can( fcsp_capability() ) ) {
			return;
		}
		// The media modal Backbone stack must be present for our frame extension.
		wp_enqueue_media();
		fcsp_register_picker_core();

		$base = plugin_dir_url( dirname( __FILE__ ) );
		wp_enqueue_style( 'firstchurch-stock-photos' );
		wp_enqueue_style( 'firstchurch-stock-media-tab', $base . 'assets/media-tab.css', array( 'firstchurch-stock-photos' ), FCSP_VERSION );
		wp_enqueue_script(
			'firstchurch-stock-media-tab',
			$base . 'assets/media-tab.js',
			array( 'firstchurch-stock-core', 'media-views' ),
			FCSP_VERSION,
			true
		);
	}
);
