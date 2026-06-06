<?php
/**
 * Featured image on single posts.
 *
 * Maranatha's single-post template (partials/content-post-full.php) renders the
 * title, meta and content but never the featured image — only the listing card
 * (partials/content-header-short.php) does. So a post with a photo teases it in
 * the News grid and the prev/next banner, then drops it on the article itself.
 *
 * This adds the featured image as a hero at the top of single blog posts WITHOUT
 * forking the parent template, by prepending it to the_content. Scoped to real
 * blog posts in the main loop (not pages, not feeds, not CTC sermons/events,
 * which manage their own imagery).
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'the_content', 'fcs_single_featured_image', 11 ); // after wpautop (10).

/**
 * Prepend the featured image to single blog-post content.
 *
 * @param string $content Post content HTML.
 * @return string
 */
function fcs_single_featured_image( $content ) {

	if (
		! is_singular( 'post' )
		|| ! in_the_loop()
		|| ! is_main_query()
		|| is_feed()
		|| ! has_post_thumbnail()
	) {
		return $content;
	}

	$image = get_the_post_thumbnail( null, 'large' );

	if ( ! $image ) {
		return $content;
	}

	return '<figure class="fcs-single-featured">' . $image . '</figure>' . $content;
}
