<?php
/**
 * First-party JavaScript: native ES modules via the WordPress Script Modules API.
 *
 * No bundler, nothing built on prod — the browser loads real ES modules and
 * WordPress (6.5+; prod runs 7.0) prints the import map + versioned URLs and
 * defers them. Each island is a small progressive-enhancement module that
 * self-guards on the markup it needs; `boot` is the single enqueued entry that
 * runs them.
 *
 * To add an island: register its module here with a `@firstchurch/<name>`
 * specifier, declare it as a dependency of `@firstchurch/boot`, and import it in
 * assets/js/boot.js. (Declaring it as a dependency is what puts it in the import
 * map and gives it a cache-busted URL.)
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register + enqueue the first-party module graph on the front end.
 *
 * Script Modules are registered with a string id used as the bare import
 * specifier in the import map (e.g. `@firstchurch/skip-link`).
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		// Older cores without the Script Modules API: bail rather than fatal.
		if ( ! function_exists( 'wp_enqueue_script_module' ) ) {
			return;
		}

		$base = get_stylesheet_directory_uri() . '/assets/js';

		// Islands (leaves of the graph).
		wp_register_script_module(
			'@firstchurch/nav',
			$base . '/islands/nav.js',
			array(),
			fcs_asset_version( 'assets/js/islands/nav.js' )
		);
		wp_register_script_module(
			'@firstchurch/skip-link',
			$base . '/islands/skip-link.js',
			array(),
			fcs_asset_version( 'assets/js/islands/skip-link.js' )
		);
		wp_register_script_module(
			'@firstchurch/worship-live',
			$base . '/islands/worship-live.js',
			array(),
			fcs_asset_version( 'assets/js/islands/worship-live.js' )
		);

		// Entry module — depends on every island it imports so they land in the
		// import map with cache-busted URLs.
		wp_register_script_module(
			'@firstchurch/boot',
			$base . '/boot.js',
			array( '@firstchurch/nav', '@firstchurch/skip-link', '@firstchurch/worship-live' ),
			fcs_asset_version( 'assets/js/boot.js' )
		);

		wp_enqueue_script_module( '@firstchurch/boot' );
	}
);
