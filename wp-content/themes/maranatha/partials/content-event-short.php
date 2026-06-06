<?php
/**
 * Short Event Content (Archive)
 *
 * This is also used in calendar when hovering or viewing on mobile as list.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Show time and description on two lines, if have time and description
/* translators: time range (%1$s) and description (%2$s) for an event */
$args['time_and_desc_format'] = __( '%1$s <span class="maranatha-entry-short-separator">/</span> <span class="maranatha-entry-short-secondary">%2$s</span>', 'maranatha' );

// Get data
// $date (localized range), $start_date, $end_date, $start_time, $end_time, $start_time_formatted, $end_time_formatted, $hide_time_range, $time (description), $time_range, $time_range_and_description, $time_range_or_description, $venue, $address, $show_directions_link, $directions_url, $map_lat, $map_lng, $map_has_coordinates, $map_type, $map_zoom, $registration_url
// Recurrence fields, $recurrence_note and $recurrence_note_short are also provided as well as $excluded_dates_note (Pro).
extract( ctfw_event_data( $args ) );

/* Translators: Separator between short entry meta items */
$separator = _x( '/', 'short entries meta', 'maranatha' );

// Are we on calendar?
$is_calendar = false;
if ( is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/events-calendar.php' ) ) {
	$is_calendar = true;
}

?>

<article data-event-id="<?php echo the_ID(); ?>" id="post-<?php the_ID(); ?>" <?php maranatha_short_post_classes( 'maranatha-event-short' ); ?>>

	<header class="maranatha-entry-short-header">

		<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-header-short' ); // show title and image ?>

		<ul class="maranatha-entry-meta maranatha-entry-short-meta">

			<?php if ( $date ) : ?>

				<li class="maranatha-event-short-date">

					<span class="maranatha-event-short-date-range maranatha-dark"><?php echo esc_html( $date ); ?></span>

					<?php if ( $recurrence_note_short && ! $is_calendar ) : ?>

						<span class="maranatha-entry-short-separator"><?php echo $separator; ?></span>

						<span class="maranatha-entry-short-secondary maranatha-entry-short-recurrence"><?php echo esc_html( wptexturize( $recurrence_note_short ) ); ?></span>

					<?php endif; ?>

				</li>

			<?php endif; ?>

			<?php if ( $time_range_and_description ) : ?>

				<li class="maranatha-event-short-time maranatha-dark">
					<?php echo wptexturize( $time_range_and_description ); ?>
				</li>

			<?php endif; ?>

		</ul>

	</header>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-excerpt' ); // show excerpt if no image ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-short' ); // show appropriate button(s) ?>

</article>
