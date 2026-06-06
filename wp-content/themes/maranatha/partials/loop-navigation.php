<?php
/**
 * Output navigation at bottom of single and multiple loops
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Left/right icons
$icon_left = '<span class="' . maranatha_get_icon_class( 'nav-left' ) . '"></span>';
$icon_right = '<span class="' . maranatha_get_icon_class( 'nav-right' ) . '"></span>';

/*********************************
 * ATTACHMENT - Back to Parent
 *********************************/

// No prev/next for gallery images since images can belong to multiple galleries.
// Instead, a lightbox plugin like Jetpack Carousel can be used for prev/next.

if ( is_attachment() ) :

?>

	<?php if ( ! empty( $post->post_parent ) && $parent_post = get_post( $post->post_parent ) ) : ?>

		<div class="maranatha-nav-left-right">
			<div class="maranatha-nav-left"><?php previous_post_link( '%link', sprintf( __( ' %s Back to %s', 'maranatha' ), $icon_left, $parent_post->post_title ) ); ?></div>
		</div>

	<?php endif; ?>

<?php

/*********************************
 * SINGLE POST - Prev / Next
 *********************************/

elseif ( is_singular() && ! is_page() && ! maranatha_loop_after_content_used() ) : // use Multiple Posts nav on "loop after content" pages

	// Get prev/next posts
	$prev_post = get_previous_post();
	$next_post = get_next_post();

	// Show only if has prev or next post
	if ( ! $prev_post && ! $next_post ) {
		return;
	}

	// Let child themes change this
	$prev_next_title_characters = apply_filters( 'maranatha_prev_next_title_characters', 50 ); // approx 2 lines

	?>

	<div class="maranatha-nav-blocks maranatha-color-main-bg<?php if ( $prev_post && $next_post ) : ?> maranatha-nav-block-has-both<?php endif; ?>">

		<div class="maranatha-nav-block maranatha-nav-block-left<?php if ( ! $prev_post ) : ?> maranatha-nav-block-empty<?php endif; ?>">

			<?php if ( $prev_post ) : ?>

				<?php
				// Get url, label and image_style
				$data_prev = maranatha_single_post_nav_data( 'previous', $prev_post );
				?>

				<?php if ( $data_prev['image_style'] ) : ?>
					<div class="maranatha-nav-block-image" style="<?php echo esc_attr( $data_prev['image_style'] ); ?>"></div>
				<?php endif; ?>

				<div class="maranatha-nav-block-content">

					<div class="maranatha-nav-block-content-columns">

						<div class="maranatha-nav-block-content-column maranatha-nav-block-content-left maranatha-nav-block-content-arrow">

							<a href="<?php echo esc_url( $data_prev['url'] ); ?>"><span class="<?php echo maranatha_get_icon_class( 'nav-left' ); ?>"></span></a>

						</div>

						<div class="maranatha-nav-block-content-column maranatha-nav-block-content-right maranatha-nav-block-content-text">

							<?php if ( $data_prev['label'] ) : ?>
								<div class="maranatha-nav-block-label"><?php echo esc_html( $data_prev['label'] ); ?></div>
							<?php endif; ?>

							<a href="<?php echo esc_url( $data_prev['url'] ); ?>" class="maranatha-nav-block-title"><?php echo esc_html( ctfw_shorten( $prev_post->post_title, $prev_next_title_characters ) ); ?></a>

						</div>

					</div>

				</div>

			<?php endif; ?>

		</div>

		<div class="maranatha-nav-block maranatha-nav-block-right<?php if ( ! $next_post ) : ?> maranatha-nav-block-empty<?php endif; ?>">

			<?php if ( $next_post ) : ?>

				<?php
				// Get url, label and image_style
				$data_next = maranatha_single_post_nav_data( 'next', $next_post );
				?>

				<?php if ( $data_next['image_style'] ) : ?>
					<div class="maranatha-nav-block-image" style="<?php echo esc_attr( $data_next['image_style'] ); ?>"></div>
				<?php endif; ?>

				<div class="maranatha-nav-block-content">

					<div class="maranatha-nav-block-content-columns">

						<div class="maranatha-nav-block-content-column maranatha-nav-block-content-left maranatha-nav-block-content-text">

							<?php if ( $data_next['label'] ) : ?>
								<div class="maranatha-nav-block-label"><?php echo esc_html( $data_next['label'] ); ?></div>
							<?php endif; ?>

							<a href="<?php echo esc_url( $data_next['url'] ); ?>" class="maranatha-nav-block-title"><?php echo esc_html( ctfw_shorten( $next_post->post_title, $prev_next_title_characters ) ); ?></a>

						</div>

						<div class="maranatha-nav-block-content-column maranatha-nav-block-content-right maranatha-nav-block-content-arrow">

							<a href="<?php echo esc_url( $data_next['url'] ); ?>"><span class="<?php echo maranatha_get_icon_class( 'nav-right' ); ?>"></span></a>

						</div>

					</div>

				</div>

			<?php endif; ?>

		</div>

	</div>

<?php

/*********************************
 * MULTIPLE POSTS - Page 1 2 3
 *********************************/

else :

	// Query to use for pagination
	$query = maranatha_loop_after_content_query();
	if ( ! $query ) {  // use "loop after content" query if available
		$query = $wp_query; // otherwise use default query
	}

?>

	<?php if ( $query->max_num_pages > 1 ) : // show only if more than 1 page ?>

		<div class="maranatha-pagination">

			<?php
			// To Do: Replace with the_posts_pagination(), new as of WP 4.1 (how to use with CPT?)
			echo paginate_links( array(
				'base' 		=> str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ), // for search and archives: https://codex.wordpress.org/Function_Reference/paginate_links#Examples
				'current' 	=> max( 1, ctfw_page_num() ), // ctfw_page_num() returns/corrects $paged so pagination works on static front page
				'total' 	=> $query->max_num_pages,
				'type' 		=> 'list',
				'prev_text'	=> $icon_left,
				'next_text'	=> $icon_right,
			) );
			?>

		</div>

	<?php endif; ?>

<?php endif; ?>