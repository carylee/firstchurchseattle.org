<?php
/**
 * First Church Seattle theme functions.
 *
 * Keep this file small. Add new behaviour by including separate files from
 * an `inc/` directory rather than letting this grow into a god-file.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache-busting version for a theme asset: the file's mtime.
 *
 * mtimes need no bumping and stay correct on their own: deploys rsync with -t
 * from a fresh CI checkout, so a shipped file's mtime changes and
 * browsers/Cloudflare refetch; local edits do the same under DDEV. The theme
 * is exempted from ops/scripts/check-asset-version-bump.sh for the same reason.
 *
 * @param string $relative Asset path relative to the theme root,
 *                         e.g. 'assets/theme.css'.
 * @return string Version string for wp_enqueue_*; '0' if the file is missing.
 */
function fcs_asset_version( $relative ) {
	$mtime = (int) @filemtime( get_stylesheet_directory() . '/' . ltrim( $relative, '/' ) );
	return $mtime ? (string) $mtime : '0';
}

/**
 * Feature modules. Keep functions.php thin — each concern lives in inc/, and
 * every inc/*.php loads automatically (alphabetical; order must not matter:
 * modules only define things and register hooks, never call each other at
 * load time). Adding a module = adding a file — nothing to edit here, so
 * concurrent theme PRs no longer collide on a shared require list.
 */
foreach ( glob( get_stylesheet_directory() . '/inc/*.php' ) ?: array() as $fcs_module ) {
	require_once $fcs_module;
}
unset( $fcs_module );

/**
 * The stylesheet. One file: the Tailwind v4 build of assets/src/ (tokens,
 * base typography, chrome, components — see assets/src/input.css). Built
 * locally via ./build-css.sh — production never compiles.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'firstchurch',
		get_stylesheet_directory_uri() . '/assets/tailwind.css',
		array(),
		fcs_asset_version( 'assets/tailwind.css' )
	);
} );

/**
 * Skip-to-content link. Rendered immediately after <body> so it's the first
 * focusable element on every page. Hidden until focused. The #main-content
 * target is injected into <main> by the skip-link island
 * (assets/js/islands/skip-link.js, enqueued from inc/scripts.php).
 */
add_action( 'wp_body_open', function () {
	?>
	<a class="fcs-skip-link screen-reader-text" href="#main-content"><?php esc_html_e( 'Skip to main content', 'firstchurch' ); ?></a>
	<?php
} );
