<?php
/**
 * Template Name: Child Pages
 *
 * Section landing page: the page's own content, then a card grid of its
 * published child pages (each with featured image, title, excerpt). Same
 * template path the old parent template used, so the ten existing page
 * assignments (About, Gather, Give, …) carry over.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<main id="fcs-content" class="fcs-main">
	<?php while ( have_posts() ) : the_post(); ?>
		<div class="fcs-container--med">

			<?php if ( fcs_has_content() ) : ?>
				<div class="fcs-measure fcs-entry">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

			<?php
			$fcs_children = get_pages(
				array(
					'parent'      => get_the_ID(),
					'sort_column' => 'menu_order,post_title',
				)
			);

			if ( $fcs_children ) :
				?>
				<div class="fcs-card-grid fcs-card-grid--three">
					<?php foreach ( $fcs_children as $fcs_child ) : ?>
						<article class="fcs-card">
							<?php if ( has_post_thumbnail( $fcs_child ) ) : ?>
								<a class="fcs-card__media" href="<?php echo esc_url( get_permalink( $fcs_child ) ); ?>" tabindex="-1" aria-hidden="true">
									<?php echo get_the_post_thumbnail( $fcs_child, 'medium_large', array( 'loading' => 'lazy' ) ); ?>
								</a>
							<?php endif; ?>
							<div class="fcs-card__body">
								<h2 class="fcs-card__title">
									<a href="<?php echo esc_url( get_permalink( $fcs_child ) ); ?>"><?php echo esc_html( get_the_title( $fcs_child ) ); ?></a>
								</h2>
								<?php
								// Manual excerpt, else a trim of the page's own text.
								$fcs_excerpt = $fcs_child->post_excerpt ?: wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $fcs_child->post_content ) ) );
								if ( $fcs_excerpt ) :
									?>
									<p class="fcs-card__excerpt"><?php echo esc_html( wp_trim_words( $fcs_excerpt, 28 ) ); ?></p>
								<?php endif; ?>
							</div>
							<div class="fcs-card__cta">
								<a href="<?php echo esc_url( get_permalink( $fcs_child ) ); ?>" class="fcs-cta-button is-fallback"><?php esc_html_e( 'View page', 'firstchurch' ); ?></a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		</div>
	<?php endwhile; ?>
</main>
<?php

get_footer();
