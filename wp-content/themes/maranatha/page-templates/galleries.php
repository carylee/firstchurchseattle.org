<?php
/**
 * Template Name: Galleries
 *
 * This template outputs a list of pages that use the Gallery template and [gallery] shortcode.
 * It uses a combination of the .gallery class for columns and .maranatha-caption-image for representing pages.
 *
 * partials/content-full.php outputs the page content.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Prepare gallery content to output
function maranatha_galleries_after_content() {

	// Get gallery posts/pages
	$galley_posts = ctfw_gallery_posts( array(
		'orderby'	=> 'date',
		'order'		=> 'desc'
	) );

	?>

	<?php if ( ! empty( $galley_posts ) ) : ?>

		<div class="maranatha-galleries-list gallery gallery-columns-3">

			<?php foreach ( $galley_posts as $post_id => $post_data ) : ?>

				<?php

				// Use first image in gallery
				$image_id = false;
				if ( ! empty( $post_data['image_ids'][0] ) ) { // use first image from first gallery in content
					$image_id = $post_data['image_ids'][0];
				}

				// Not using key prevents Theme Check false positive
				$image_count = $post_data['image_count'];

				?>

				<div class="maranatha-galleries-item gallery-item maranatha-caption-image <?php if ( ! $image_id ) : ?> maranatha-caption-image-no-image<?php endif; ?>">

					<a href="<?php echo esc_url( get_permalink( $post_data['post']->ID ) ); ?>" title="<?php echo esc_attr( get_the_title( $post_data['post']->ID ) ); ?>">

						<?php if ( $image_id ) : // valid image specified ?>
							<?php echo wp_get_attachment_image( $image_id, 'maranatha-rect-medium', false, array( 'class' => 'maranatha-image') ); ?>
						<?php else : // use transparent placeholder thumbnail of proper proportion ?>
							<img class="gallery-icon" src="<?php echo apply_filters( 'maranatha_thumb_placeholder_url', CTFW_THEME_URL . '/images/thumb-placeholder.png' ); ?>" alt="">
						<?php endif; ?>

						<div class="maranatha-caption-image-caption">

							<div class="maranatha-caption-image-title">
								<?php echo wptexturize( get_the_title( $post_data['post']->ID ) ); ?>
							</div>

							<?php if ( isset( $image_count ) ) : ?>
								<div class="maranatha-caption-image-description">
									<?php printf( _n( '1 Photo', '%d Photos', $image_count, 'maranatha' ), $image_count ); ?>
								</div>
							<?php endif; ?>

						</div>

					</a>

				</div>

			<?php endforeach; ?>

		</div>

	<?php endif; ?>

	<?php

}

// Insert content after the_content() in content.php
add_action( 'maranatha_after_content', 'maranatha_galleries_after_content' );

// Load main template to show the page
locate_template( 'index.php', true );
