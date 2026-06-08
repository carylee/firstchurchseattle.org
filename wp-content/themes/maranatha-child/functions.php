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
	define( 'FCS_CHILD_VERSION', '0.6.10' );
}

/**
 * Feature modules. Keep functions.php thin — each concern lives in inc/.
 */
require_once get_stylesheet_directory() . '/inc/announcements-cta.php';
require_once get_stylesheet_directory() . '/inc/single-featured-image.php';
require_once get_stylesheet_directory() . '/inc/block-editor-fixes.php';
require_once get_stylesheet_directory() . '/inc/happenings-block.php';

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
 * The theme renders <main id="maranatha-content"> on most pages and
 * <main id="maranatha-home-main"> on the homepage. Rather than override either
 * template, inject a tiny inline script that adds id="main-content" + tabindex
 * to whichever one exists. Runs in <head> so the anchor is wired before paint.
 */
add_action( 'wp_head', function () {
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var m = document.querySelector('main[id]');
		if (m && !document.getElementById('main-content')) {
			m.id = m.id; // keep original
			m.setAttribute('tabindex', '-1');
			// Add an additional anchor target so the skip link works regardless of which main id is in use.
			var anchor = document.createElement('span');
			anchor.id = 'main-content';
			anchor.setAttribute('tabindex', '-1');
			anchor.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);';
			m.insertBefore(anchor, m.firstChild);
		}
	});
	</script>
	<?php
}, 1 );
