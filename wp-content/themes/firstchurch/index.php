<?php
/**
 * Fallback template: archives, search results, and anything without a more
 * specific template. Singular content has page.php / single.php; this renders
 * a card grid of whatever the query returned.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<main id="fcs-content" class="fcs-main">
	<div class="fcs-container--med">

		<?php
		// Term descriptions add context under the banner title on archives.
		$fcs_desc = is_archive() ? get_the_archive_description() : '';
		if ( $fcs_desc ) :
			?>
			<div class="fcs-archive-header">
				<div class="fcs-archive-desc"><?php echo wp_kses_post( $fcs_desc ); ?></div>
			</div>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>

			<div class="fcs-card-grid">
				<?php
				while ( have_posts() ) {
					the_post();
					get_template_part( 'partials/card' );
				}
				?>
			</div>

			<nav class="fcs-pagination" aria-label="<?php esc_attr_e( 'Posts navigation', 'firstchurch' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'type'      => 'list',
							'prev_text' => __( '← Newer', 'firstchurch' ),
							'next_text' => __( 'Older →', 'firstchurch' ),
						)
					) ?: ''
				);
				?>
			</nav>

		<?php else : ?>

			<div class="fcs-no-results">
				<p>
					<?php
					if ( is_search() ) {
						esc_html_e( 'Nothing matched that search. Try different words?', 'firstchurch' );
					} else {
						esc_html_e( 'Nothing to show here yet.', 'firstchurch' );
					}
					?>
				</p>
				<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="fcs-loop-search"><?php esc_html_e( 'Search for:', 'firstchurch' ); ?></label>
					<input type="search" id="fcs-loop-search" name="s" placeholder="<?php esc_attr_e( 'Search the site…', 'firstchurch' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
					<button type="submit" class="btn-primary"><?php esc_html_e( 'Search', 'firstchurch' ); ?></button>
				</form>
			</div>

		<?php endif; ?>

	</div>
</main>
<?php

get_footer();
