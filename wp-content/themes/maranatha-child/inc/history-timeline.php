<?php
/**
 * History timeline styles.
 *
 * The timeline page (/about/history/timeline/) is plain block content whose
 * markup carries fc-timeline / fc-tl-* classes — see assets/timeline.css for
 * the markup contract. This enqueues that stylesheet on any page using it.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_page() ) {
		return;
	}

	$post = get_post();
	if ( ! $post || false === strpos( $post->post_content, 'fc-timeline' ) ) {
		return;
	}

	wp_enqueue_style(
		'maranatha-child-timeline',
		get_stylesheet_directory_uri() . '/assets/timeline.css',
		array( 'maranatha-child-mobile' ),
		FCS_CHILD_VERSION
	);
}, 20 );
