<?php
/**
 * Template tags — small first-party helpers the templates share.
 *
 * (Historically this file held replicas of parent-theme helpers during the
 * theme-independence migration; the theme is standalone now and these are
 * simply ours.)
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page title for the banner: resolves singular titles, archives, search
 * and 404, with a " – Page N" suffix on paged views.
 *
 * @return string
 */
function fcs_page_title(): string {
	if ( is_singular() ) {
		$title = get_the_title();
	} elseif ( is_home() ) {
		$title = single_post_title( '', false );
	} elseif ( is_search() ) {
		/* translators: %s: search query */
		$title = sprintf( __( 'Search: %s', 'firstchurch' ), get_search_query() );
	} elseif ( is_404() ) {
		$title = __( 'Page Not Found', 'firstchurch' );
	} elseif ( is_archive() ) {
		$title = get_the_archive_title();
		// Strip core's "Category:" style prefixes — the banner needs only the name.
		$title = preg_replace( '/^[^:]+:\s*/', '', $title );
	} else {
		$title = get_bloginfo( 'name' );
	}

	$paged = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	if ( $paged >= 2 ) {
		/* translators: %d: page number */
		$title .= ' ' . sprintf( __( '– Page %d', 'firstchurch' ), $paged );
	}

	return (string) $title;
}

/**
 * True when the current post has real body content.
 *
 * Strips tags but keeps media/embeds so an image-only post still counts, and
 * treats any block (`wp:`) markup as content.
 *
 * @return bool
 */
function fcs_has_content(): bool {
	$content = trim( get_the_content() );

	return (bool) (
		strip_tags( $content, '<img><iframe><script><embed><audio><video>' )
		|| preg_match( '/wp\:/', $content )
	);
}
