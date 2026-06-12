<?php
/**
 * Single post (announcement) template.
 *
 * The featured-image hero is prepended by inc/single-featured-image.php (a
 * the_content filter), and the announcement CTA button renders on the
 * `fcs_after_content` hook (inc/announcements-cta.php).
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
		<div class="fcs-container--text">
			<p class="fcs-page-meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				<?php
				$fcs_cats = get_the_category_list( ', ' );
				if ( $fcs_cats ) {
					echo ' · ' . $fcs_cats; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-built link list.
				}
				?>
			</p>
			<div class="fcs-entry">
				<?php the_content(); ?>
			</div>
			<?php do_action( 'fcs_after_content' ); ?>
		</div>
	<?php endwhile; ?>
</main>
<?php

get_footer();
