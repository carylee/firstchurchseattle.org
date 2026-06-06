<?php
/**
 * Template Name: Sermon Speakers
 *
 * This shows a page with sermon speakers.
 *
 * partials/content-full.php outputs the page content.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Prepare sermon speakers to output after page content
function maranatha_sermon_speakers_after_content() {

	// Get speakers
	$speakers = wp_list_categories( array(
		'taxonomy' => 'ctc_sermon_speaker',
		'hierarchical' => false,
		'show_count' => true,
		'title_li' => '',
		'echo' => false,
	) );

	?>

	<div id="maranatha-sermon-speakers" class="maranatha-sermon-index">

		<?php
		// Buttons for switching between indexes
		get_template_part( CTFW_THEME_PARTIAL_DIR . '/sermon-index-header' );
		?>

		<?php if ( $speakers ) : ?>

			<ul id="maranatha-sermon-speakers-list" class="maranatha-sermon-index-list maranatha-sermon-index-list-three-columns">
				<?php echo $speakers; ?>
			</ul>

		<?php else : ?>

			<p id="maranatha-sermon-index-none">
				<?php _e( 'There are no speakers to show.', 'maranatha' ); ?>
			</p>

		<?php endif; ?>

	</div>

	<?php

}

// Insert content after the_content() in content.php
add_action( 'maranatha_after_content', 'maranatha_sermon_speakers_after_content' );

// Load main template to show the page
locate_template( 'index.php', true );