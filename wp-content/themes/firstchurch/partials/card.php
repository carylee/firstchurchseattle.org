<?php
/**
 * Loop card — one post/page in a listing (blog index, search, archives).
 *
 * Same .fcs-card component the Happenings cards use, with an optional
 * thumbnail on top (.fcs-card__media). Call inside the loop:
 * get_template_part( 'partials/card' ).
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<article class="fcs-card">

	<?php if ( has_post_thumbnail() ) : ?>
		<a class="fcs-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
		</a>
	<?php endif; ?>

	<div class="fcs-card__body">
		<h3 class="fcs-card__title">
			<a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ?: __( '(Untitled)', 'firstchurch' ) ); ?></a>
		</h3>
		<p class="fcs-card__meta">
			<?php echo esc_html( get_the_date() ); ?>
			<?php if ( 'post' !== get_post_type() ) : ?>
				· <?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? '' ); ?>
			<?php endif; ?>
		</p>
		<?php if ( has_excerpt() || fcs_has_content() ) : ?>
			<p class="fcs-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28 ) ); ?></p>
		<?php endif; ?>
	</div>

	<div class="fcs-card__cta">
		<a href="<?php the_permalink(); ?>" class="fcs-cta-button is-fallback"><?php esc_html_e( 'Read more', 'firstchurch' ); ?></a>
	</div>

</article>
