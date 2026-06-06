<?php
/**
 * Block editor (Gutenberg) appearance fixes.
 *
 * The parent Maranatha theme ships css/admin/block-editor.css (via
 * add_theme_support('ctfw-editor-styles')) authored for the pre-iframe Gutenberg
 * DOM circa 2019. Under WordPress 6.x/7.0 it makes the post editor unusable: the
 * title is styled color:#fff (it used to sit over a dark cover image) so it's
 * white-on-white, and the theme opts into editor styles without declaring a base
 * font-family, so the content iframe falls back to the browser-default serif.
 *
 * We can't patch the parent theme (third-party, mirrored, overwritten on every
 * pull). Instead enqueue a tiny corrective stylesheet into the block editor,
 * declared dependent on the parent's 'ctfw-block-editor' handle so it loads last
 * and wins. See assets/block-editor-fixes.css for the rules.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_style(
		'maranatha-child-block-editor-fixes',
		get_stylesheet_directory_uri() . '/assets/block-editor-fixes.css',
		array( 'ctfw-block-editor' ), // Parent's editor CSS; load after it so we win.
		FCS_CHILD_VERSION
	);
}, 20 );
