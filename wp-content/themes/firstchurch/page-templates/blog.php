<?php
/**
 * Template Name: Blog
 *
 * The /news/ page: the page's own intro content, then a paginated card grid
 * of posts. Same template path the old parent template used
 * (page-templates/blog.php), so the existing page assignment carries over.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$fcs_paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$fcs_posts = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'paged'               => $fcs_paged,
		'ignore_sticky_posts' => true,
	)
);

?>
<main id="fcs-content" class="fcs-main">
	<div class="fcs-container--med">

		<?php
		while ( have_posts() ) :
			the_post();
			if ( fcs_has_content() ) :
				?>
				<div class="fcs-measure fcs-entry">
					<?php the_content(); ?>
				</div>
				<?php
			endif;
		endwhile;
		?>

		<?php if ( $fcs_posts->have_posts() ) : ?>

			<div class="fcs-card-grid">
				<?php
				while ( $fcs_posts->have_posts() ) {
					$fcs_posts->the_post();
					get_template_part( 'partials/card' );
				}
				wp_reset_postdata();
				?>
			</div>

			<nav class="fcs-pagination" aria-label="<?php esc_attr_e( 'Posts navigation', 'firstchurch' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'total'     => (int) $fcs_posts->max_num_pages,
							'current'   => $fcs_paged,
							'type'      => 'list',
							'prev_text' => __( '← Newer', 'firstchurch' ),
							'next_text' => __( 'Older →', 'firstchurch' ),
						)
					) ?: ''
				);
				?>
			</nav>

		<?php else : ?>
			<p class="fcs-no-results"><?php esc_html_e( 'No news yet — check back soon.', 'firstchurch' ); ?></p>
		<?php endif; ?>

	</div>
</main>
<?php

get_footer();
