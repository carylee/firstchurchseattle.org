<?php
/**
 * Template Name: Child Pages
 *
 * This shows a page listing child pages (pages having the page as parent).
 *
 * partials/content-full.php outputs the page content.
 * partials/content-short.php outputs content for each child page in the loop.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Query events that ended before today
function maranatha_child_pages_loop_after_content() {

	global $post;

	return new WP_Query( array(
		'post_type'			=> 'page',
		'post_parent'		=> $post->ID,
		'paged'				=> ctfw_page_num(), // returns/corrects $paged so pagination works on static front page
		'orderby'			=> 'menu_order',
		'order'				=> 'ASC'
	) );

}

// Make query available via filter
add_filter( 'maranatha_loop_after_content_query', 'maranatha_child_pages_loop_after_content' );

// Load main template to show the page
locate_template( 'index.php', true );