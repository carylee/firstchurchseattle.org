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
	} elseif ( is_post_type_archive() ) {
		$title = post_type_archive_title( '', false );
	} elseif ( is_archive() ) {
		// Core wraps the term name in markup; the banner escapes its output,
		// so take plain text and strip the "Category:" style prefix.
		$title = wp_strip_all_tags( get_the_archive_title() );
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

/**
 * The small gold context line above the banner title: section (parent page),
 * post date for articles, or content type for archives. Empty string = none.
 *
 * @return string
 */
function fcs_banner_kicker(): string {
	if ( is_singular( 'post' ) ) {
		return __( 'News', 'firstchurch' ) . ' · ' . get_the_date();
	}
	if ( is_page() ) {
		$parent = wp_get_post_parent_id( get_the_ID() );
		return $parent ? (string) get_the_title( $parent ) : '';
	}
	if ( is_search() ) {
		return __( 'Search', 'firstchurch' );
	}
	if ( is_archive() ) {
		return __( 'Archive', 'firstchurch' );
	}
	return '';
}

/**
 * Tag the Give menu item so the nav can style it as the one filled pill.
 */
add_filter(
	'nav_menu_css_class',
	function ( $classes, $item ) {
		if ( 'give' === sanitize_title( $item->title ) ) {
			$classes[] = 'fcs-menu-give';
		}
		return $classes;
	},
	10,
	2
);
