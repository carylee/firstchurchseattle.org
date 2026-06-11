<?php
/**
 * Maranatha Child theme functions.
 *
 * Keep this file small. Add new behaviour by including separate files from
 * an `inc/` directory rather than letting this grow into a god-file.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache-busting version for a child-theme asset: the file's mtime.
 *
 * Replaces the hand-bumped FCS_CHILD_VERSION constant — every theme PR had to
 * edit the same line, making functions.php a standing merge conflict. mtimes
 * need no bumping and stay correct on their own: deploys rsync with -t from a
 * fresh CI checkout, so a shipped file's mtime changes and browsers/Cloudflare
 * refetch; local edits do the same under DDEV. The child theme is exempted
 * from ops/scripts/check-asset-version-bump.sh for the same reason.
 *
 * @param string $relative Asset path relative to the child theme root,
 *                         e.g. 'assets/mobile.css'.
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
 * Enqueue parent + child stylesheets.
 *
 * Important quirk of Maranatha: the parent registers its main stylesheet under
 * the handle `maranatha-style` using `get_stylesheet_uri()`, which — when a
 * child theme is active — points at the *child's* style.css (empty) instead
 * of the parent's. Left alone, that would silently drop ~6500 lines of parent
 * CSS and leave the site unstyled. We repoint the handle at the parent file
 * after the parent's enqueue runs (default priority 10).
 *
 * The child's own style.css is intentionally empty; mobile.css holds the
 * actual customizations.
 */
add_action( 'wp_enqueue_scripts', function () {
	$parent_handle  = 'maranatha-style';
	$parent_version = wp_get_theme( get_template() )->get( 'Version' );

	// Repoint the parent's main stylesheet at the actual parent file.
	wp_dequeue_style( $parent_handle );
	wp_deregister_style( $parent_handle );
	wp_enqueue_style(
		$parent_handle,
		get_template_directory_uri() . '/style.css',
		array(),
		$parent_version
	);

	wp_enqueue_style(
		'maranatha-child-mobile',
		get_stylesheet_directory_uri() . '/assets/mobile.css',
		array( $parent_handle ),
		fcs_asset_version( 'assets/mobile.css' )
	);

	// Tailwind v4 compiled output. Used by custom templates (header-banner,
	// page-worship-live) and any future Tailwind-class-using markup.
	// Built locally via ./build-css.sh — production never compiles.
	if ( file_exists( get_stylesheet_directory() . '/assets/tailwind.css' ) ) {
		wp_enqueue_style(
			'maranatha-child-tailwind',
			get_stylesheet_directory_uri() . '/assets/tailwind.css',
			array( 'maranatha-child-mobile' ),
			fcs_asset_version( 'assets/tailwind.css' )
		);
	}

	// Visual-polish overrides. Enqueued LAST (depends on whichever of mobile /
	// tailwind is present) so its site-wide refinements win the cascade without
	// !important — see assets/polish.css.
	$polish_deps = array( 'maranatha-child-mobile' );
	if ( file_exists( get_stylesheet_directory() . '/assets/tailwind.css' ) ) {
		$polish_deps[] = 'maranatha-child-tailwind';
	}
	wp_enqueue_style(
		'maranatha-child-polish',
		get_stylesheet_directory_uri() . '/assets/polish.css',
		$polish_deps,
		fcs_asset_version( 'assets/polish.css' )
	);

	// System-preference dark mode. A single @media (prefers-color-scheme: dark)
	// override layer that flips the semantic tokens defined in mobile.css and
	// re-colours the parent theme's content surfaces. Enqueued LAST (depends on
	// polish) so its overrides win the cascade by source order. Pure CSS — no
	// toggle, no JS. See assets/dark-mode.css.
	wp_enqueue_style(
		'maranatha-child-dark-mode',
		get_stylesheet_directory_uri() . '/assets/dark-mode.css',
		array( 'maranatha-child-polish' ),
		fcs_asset_version( 'assets/dark-mode.css' )
	);
}, 20 );

/**
 * Skip-to-content link. Rendered immediately after <body> so it's the first
 * focusable element on every page. Hidden until focused. Targets whichever
 * <main id="..."> the current template uses (homepage uses
 * #maranatha-home-main; everything else uses #maranatha-content).
 */
add_action( 'wp_body_open', function () {
	?>
	<a class="fcs-skip-link screen-reader-text" href="#main-content"><?php esc_html_e( 'Skip to main content', 'maranatha-child' ); ?></a>
	<?php
} );

/**
 * The skip link's #main-content target is injected by the skip-link island
 * (assets/js/islands/skip-link.js), enqueued as an ES module from inc/scripts.php
 * — replacing what used to be an inline wp_head script here. Moving it out of an
 * inline <script> also unblocks a stricter Content-Security-Policy later.
 */
