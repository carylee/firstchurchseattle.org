<?php
/**
 * Events Widget Template
 *
 * Produces output for appropriate widget class in framework.
 * $this, $instance (sanitized field values) and $args are available.
 *
 * $this->ctfw_get_posts() can be used to produce a query according to widget field values.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// HTML Before
echo $args['before_widget'];

// Title
$title = apply_filters( 'widget_title', $instance['title'] );
if ( ! empty( $title ) ) {
	echo $args['before_title'] . $title . $args['after_title'];
}

// Get posts
$posts = ctfw_get_events( $instance ); // get events based on options - upcoming/past, limit, etc.

// Loop posts
foreach ( $posts as $post ) : setup_postdata( $post );

	// Get data
	// $date (localized range), $start_date, $end_date, $start_time, $end_time, $start_time_formatted, $end_time_formatted, $hide_time_range, $time (description), $time_range, $time_range_and_description, $time_range_or_description, $venue, $address, $show_directions_link, $directions_url, $map_lat, $map_lng, $map_has_coordinates, $map_type, $map_zoom
	// Recurrence fields, $recurrence_note and $recurrence_note_short are also provided as well as $excluded_dates_note (Pro).
	extract( ctfw_event_data( $args ) );

	// Categories
	$categories = get_the_term_list( $post->ID, 'ctc_event_category', '', __( ', ', 'maranatha' ) );

	// Show content
	$show_date = $instance['show_date'] && $date ? true : false;
	$show_time = $instance['show_time'] && $time_range_or_description ? true : false;
	$show_category = $instance['show_category'] && $categories ? true : false;
	$show_meta = ( $show_date || $show_time || $show_category ) ? true : false;

?>

	<article <?php maranatha_short_post_classes( 'maranatha-event-short' ); ?>>

		<div class="maranatha-entry-short-header">

			<?php if ( $instance['show_image'] && has_post_thumbnail() ) : ?>

				<div class="maranatha-entry-short-image maranatha-hover-image">
					<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
						<?php the_post_thumbnail( 'post-thumbnail' ); ?>
					</a>
				</div>

			<?php endif; ?>

			<?php if ( ctfw_has_title() ) : ?>

				<h3>
					<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
				</h3>

			<?php endif; ?>

			<?php if ( $show_meta ) : ?>

				<ul class="maranatha-entry-meta maranatha-entry-short-meta">

					<?php if ( $show_date ) : ?>
						<li class="maranatha-event-short-date maranatha-dark">
							<?php echo esc_html( $date ); ?>
						</li>
					<?php endif; ?>

					<?php if ( $show_time ) : ?>
						<li class="maranatha-event-short-time maranatha-dark">
							<?php echo wptexturize( $time_range_or_description ); // show Time Range if given; otherwise Description (not both) ?>
						</li>
					<?php endif; ?>

					<?php if ( $show_category ) : ?>
						<li class="maranatha-event-short-category">
							<?php echo $categories; ?>
						</li>
					<?php endif; ?>

				</ul>

			<?php endif; ?>

		</div>

		<?php if ( get_the_excerpt() && ! empty( $instance['show_excerpt'] )): ?>

			<div class="maranatha-entry-content maranatha-entry-content-short">
				<?php maranatha_entry_widget_excerpt(); ?>
			</div>

		<?php endif; ?>

	</article>

<?php

// End Loop
endforeach;

// Reset post data
wp_reset_postdata();

// No items found
if ( empty( $posts ) ) {

	?>
	<div>
		<?php _ex( 'There are no events to show.', 'events widget', 'maranatha' ); ?>
	</div>
	<?php

}

// HTML After
echo $args['after_widget'];