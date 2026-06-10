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
 * Version string for cache-busting child assets. Bump when the CSS/JS changes.
 */
if ( ! defined( 'FCS_CHILD_VERSION' ) ) {
	define( 'FCS_CHILD_VERSION', '0.18.1' );
}

/**
 * Feature modules. Keep functions.php thin — each concern lives in inc/.
 */
require_once get_stylesheet_directory() . '/inc/announcements-cta.php';
require_once get_stylesheet_directory() . '/inc/single-featured-image.php';
require_once get_stylesheet_directory() . '/inc/block-editor-fixes.php';
require_once get_stylesheet_directory() . '/inc/happenings-block.php';
require_once get_stylesheet_directory() . '/inc/font-optimization.php';
require_once get_stylesheet_directory() . '/inc/sermon-structured-data.php';
require_once get_stylesheet_directory() . '/inc/event-structured-data.php';
require_once get_stylesheet_directory() . '/inc/footer.php';
require_once get_stylesheet_directory() . '/inc/redirects.php';
require_once get_stylesheet_directory() . '/inc/scripts.php';

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
		FCS_CHILD_VERSION
	);

	// Tailwind v4 compiled output. Used by custom templates (header-banner,
	// page-worship-live) and any future Tailwind-class-using markup.
	// Built locally via ./build-css.sh — production never compiles.
	if ( file_exists( get_stylesheet_directory() . '/assets/tailwind.css' ) ) {
		wp_enqueue_style(
			'maranatha-child-tailwind',
			get_stylesheet_directory_uri() . '/assets/tailwind.css',
			array( 'maranatha-child-mobile' ),
			FCS_CHILD_VERSION
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
		FCS_CHILD_VERSION
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
