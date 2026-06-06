<?php
/**
 * Full Event Content (Single)
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

// Show time and description on two lines, if have time and description
/* translators: time range (%1$s) and description (%2$s) for an event */
$args['time_and_desc_format'] = __( '<div class="maranatha-dark">%1$s</div> <div class="maranatha-event-time-description maranatha-entry-full-meta-second-line">%2$s</div>', 'maranatha' );

// Get data
// $date (localized range), $start_date, $end_date, $start_time, $end_time, $start_time_formatted, $end_time_formatted, $hide_time_range, $time (description), $time_range, $time_range_and_description, $time_range_or_description, $venue, $address, $show_directions_link, $directions_url, $map_lat, $map_lng, $map_has_coordinates, $map_type, $map_zoom, $registration_url
// Recurrence fields, $recurrence_note and $recurrence_note_short are also provided as well as $excluded_dates_note (Pro).
extract( ctfw_event_data( $args ) );

// Categories
$categories = get_the_term_list(
	$post->ID,
	'ctc_event_category',
	'',
	/* translators: used between list items, there is a space after the comma */
	__( ', ', 'maranatha' )
);

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'maranatha-entry-full maranatha-event-full' ); ?>>

	<?php
	// Load map section (also used on homepage and footer)
	get_template_part( CTFW_THEME_PARTIAL_DIR . '/map-section' );
	?>

	<header class="maranatha-entry-full-header">

		<?php
		// This is visible only to screenreaders.
		// Page title is shown in banner. This is for proper HTML5 and Outline
		if (ctfw_has_title()) :
		?>

			<h1 id="maranatha-main-title">
				<?php maranatha_title_paged(); ?>
			</h1>

		<?php endif; ?>

		<?php if ($date || $time || $categories || $registration_url || ( ! $map_has_coordinates && ( $address || $venue ) )) : ?>

			<ul class="maranatha-entry-meta maranatha-entry-full-meta">

				<?php if ($date) : ?>

					<li class="maranatha-entry-full-date maranatha-event-date">

						<div class="maranatha-entry-full-meta-label"><?php _ex( 'Date', 'event', 'maranatha' ); ?></div>

						<div class="maranatha-dark"><?php echo esc_html( $date ); ?></div>

						<?php if ($recurrence_note) : ?>

							<div id="maranatha-event-recurrence" class="maranatha-entry-full-meta-second-line">

								<?php if (strlen( $recurrence_note ) != strlen( $recurrence_note_short )) : ?>

									<a href="#" title="<?php echo esc_attr( $recurrence_note ); ?>">
										<?php echo $recurrence_note_short; ?>
									</a>

								<?php else : ?>
									<?php echo $recurrence_note_short; ?>
								<?php endif; ?>

							</div>

						<?php elseif ($excluded_dates_note) : ?>

							<div id="maranatha-event-excluded-dates" class="maranatha-entry-full-meta-second-line">

								<a href="#" title="<?php echo esc_attr( $excluded_dates_note ); ?>">
									<?php esc_html_e( 'Excluded Dates', 'maranatha' ) ?>
								</a>

							</div>

						<?php endif; ?>

					</li>

				<?php endif; ?>

				<?php if ($time_range_and_description) : ?>

					<li id="maranatha-event-time">

						<div class="maranatha-entry-full-meta-label"><?php _ex( 'Time', 'event', 'maranatha' ); ?></div>

						 <div<?php if (! preg_match( '/maranatha-dark/', $time_range_and_description )) : ?> class="maranatha-dark"<?php endif; ?>><?php echo wptexturize( $time_range_and_description ); ?></div>

					</li>

				<?php endif; ?>

				<?php
				// No map so show Address + Venue in meta
				if (! $map_has_coordinates) :
				?>

					<?php if ($address) : ?>

						<li id="maranatha-event-address">

							<div class="maranatha-entry-full-meta-label"><?php _e( 'Address', 'maranatha' ); ?></div>

							<div class="maranatha-dark"><?php echo nl2br( esc_html( wptexturize( $address ) ) ); ?></div>

							<?php if ($directions_url) : ?>

								<div class="maranatha-entry-full-meta-second-line">
									<a href="<?php echo esc_url( $directions_url ); ?>" class="maranatha-map-button-directions" target="_blank" rel="noopener noreferrer"><?php _e( 'Directions', 'maranatha' ); ?></a>
								</div>

							<?php endif; ?>

						</li>

					<?php endif; ?>

					<?php if ($venue) : ?>

						<li id="maranatha-event-venue">

							<div class="maranatha-entry-full-meta-label"><?php _e( 'Venue', 'maranatha' ); ?></div>

							<div class="maranatha-dark"><?php echo esc_html( wptexturize( $venue ) ); ?></div>

						</li>

					<?php endif; ?>

				<?php endif; ?>

				<?php if ($categories) : ?>

					<li id="maranatha-event-category">
						<div class="maranatha-entry-full-meta-label"><?php _e( 'Category', 'maranatha' ); ?></div>
						<?php echo $categories; ?>
					</li>

				<?php endif; ?>

				<?php if ($registration_url) : ?>

					<li id="maranatha-event-registration">

						<a href="<?php echo esc_url( $registration_url ); ?>" target="_blank" rel="noopener noreferrer" class="maranatha-button">
							<?php echo esc_html( _x( 'Register', 'event registration', 'maranatha' ) ); ?>
						</a>

					</li>

				<?php endif; ?>

			</ul>

		<?php endif; ?>

	</header>

	<?php if (ctfw_has_content()) : // might not be any content, so let header sit flush with bottom ?>

		<div id="maranatha-event-content" class="maranatha-entry-content maranatha-entry-full-content maranatha-centered-small">

			<?php the_content(); ?>

			<?php do_action( 'maranatha_after_content' ); ?>

		</div>

	<?php endif; ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-full' ); // multipage nav, term lists, "Edit" button, etc. ?>

</article>
