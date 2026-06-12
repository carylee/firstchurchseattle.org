<?php
/**
 * Block editor (Gutenberg) appearance: a minimal first-party editor
 * stylesheet (assets/editor.css) — sans base + legible title + brand
 * headings. See that file for the rules.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_style(
		'firstchurch-editor',
		get_stylesheet_directory_uri() . '/assets/editor.css',
		array(),
		fcs_asset_version( 'assets/editor.css' )
	);
} );
