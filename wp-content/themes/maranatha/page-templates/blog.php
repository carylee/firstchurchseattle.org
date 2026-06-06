<?php
/**
 * Template Name: Blog
 *
 * This shows a page with custom loop after the content.
 *
 * partials/content-full.php outputs the page content.
 * partials/content-post-short.php outputs content for each post in the loop.
 */

// Query blog posts
function maranatha_blog_loop_after_content() {

	return new WP_Query( array(
		'post_type'	=> 'post',
		'paged'		=> ctfw_page_num() // returns/corrects $paged so pagination works on static front page
	) );

}

// Make query available via filter
add_filter( 'maranatha_loop_after_content_query', 'maranatha_blog_loop_after_content' );

// Load main template to show the page
locate_template( 'index.php', true );
