<?php
/**
 * Template Name: Sermon Dates
 *
 * This shows a page with sermon years and months.
 *
 * partials/content-full.php outputs the page content.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Prepare sermon dates to output after page content
function maranatha_sermon_dates_after_content() {

	// Get month archives
	$months = ctfw_get_month_archives( 'ctc_sermon' );
	$months = array_reverse( $months ); // months early to late

	// Organized by year
	$dates = array();
	foreach ( $months as $month ) {
		$dates[$month->year][] = $month;
	}
	krsort( $dates ); // years late to early

	?>

	<div id="maranatha-sermon-dates" class="maranatha-sermon-index">

		<?php
		// Buttons for switching between indexes
		get_template_part( CTFW_THEME_PARTIAL_DIR . '/sermon-index-header' );
		?>

		<?php if ( $dates ) : ?>

			<div id="maranatha-sermon-dates-list">

				<?php foreach ( $dates as $year => $months ) : ?>

					<h2><?php echo esc_html( $year ); ?></h2>

					<ul>

						<?php foreach ( $months as $month ) : ?>

							<li>

								<a href="<?php echo esc_url( $month->url ); ?>">

									<?php
									echo date_i18n(
										/* translators: F is month for sermon dates index (uses PHP date format) */
										_x( 'F', 'sermon dates index', 'maranatha' ),
										mktime( 0, 0, 0, $month->month, 1, $month->year )
									);
									?>

								</a>

								<span class="maranatha-sermon-dates-count"><?php echo esc_html( $month->count ); ?></span>

							</li>

						<?php endforeach; ?>

					</ul>

				<?php endforeach; ?>

			</div>

		<?php else: ?>

			<p id="maranatha-sermon-index-none">
				<?php _e( 'There are no dates to show.', 'maranatha' ); ?>
			</p>

		<?php endif; ?>

	</div>

	<?php

}

// Insert content after the_content() in content.php
add_action( 'maranatha_after_content', 'maranatha_sermon_dates_after_content' );

// Load main template to show the page
locate_template( 'index.php', true );