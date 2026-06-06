<?php
/**
 * Locations Widget Template
 *
 * Produces output for appropriate widget class in framework.
 * $this, $instance (sanitized field values) and $args are available.
 *
 * $this->ctfw_get_posts() can be used to produce a query according to widget field values.
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

// HTML Before
echo $args['before_widget'];

// Title
$title = apply_filters( 'widget_title', $instance['title'] );
if (! empty( $title )) {
	echo $args['before_title'] . $title . $args['after_title'];
}

// Get posts
$posts = $this->ctfw_get_posts(); // widget's default query according to field values

// Loop Posts
foreach ($posts as $post) : setup_postdata( $post );

	// Get data
	// $address, $show_directions_link, $directions_url, $phone, $email, $times, $map_lat, $map_lng, $map_has_coordinates, $map_type, $map_zoom
	extract( ctfw_location_data() );

	// Show content
	$show_address = $instance['show_address'] && $address ? true : false;
	$show_phone = $instance['show_phone'] && $phone ? true : false;
	$show_email = $instance['show_email'] && $email ? true : false;
	$show_times = $instance['show_times'] && $times ? true : false;
	$show_meta = ( $show_address || $show_phone || $show_email || $show_times ) ? true : false;

	// Image and map?
	$img_and_map_class = '';
	if ($instance['show_image'] && has_post_thumbnail() && $instance['show_map']) {
		$img_and_map_class = ' maranatha-location-short-image-and-map';
	}

?>

	<article <?php maranatha_short_post_classes( 'maranatha-location-short' . $img_and_map_class ); ?>>

		<div class="maranatha-entry-short-header">

			<?php if ($instance['show_image'] && has_post_thumbnail()) : ?>

				<div class="maranatha-entry-short-image maranatha-hover-image">
					<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
						<?php the_post_thumbnail( 'post-thumbnail' ); ?>
					</a>
				</div>

			<?php endif; ?>

			<?php
			if ($instance['show_map']) :?>

				<?php

				// Generate Google Map image tag
				// This is HiDPI-ready with double scale constrained by width/height attributes
				$img_tag = ctfw_google_map_image( array(
					'latitude'		=> $map_lat,
					'longitude'		=> $map_lng,
					'type'			=> $map_type,
					'zoom'			=> $map_zoom,
					'width'			=> 500,
					'height'		=> 125
				) );
				if ($img_tag) :

				?>

					<div class="maranatha-entry-short-map maranatha-entry-short-image">
						<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php echo $img_tag; ?></a>
					</div>

				<?php endif; ?>

			<?php endif; ?>

			<?php if (ctfw_has_title()) : ?>

				<h3>
					<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
				</h3>

			<?php endif; ?>

			<?php if ($show_meta) : ?>

				<ul class="maranatha-entry-meta maranatha-entry-short-meta">

					<?php if ($show_address) : ?>
						<li class="maranatha-location-short-address maranatha-dark">
							<?php echo wptexturize( ctfw_address_one_line( $address ) ); ?>
						</li>
					<?php endif; ?>

					<?php if ($show_phone) : ?>
						<li class="maranatha-location-short-phone maranatha-dark">
							<?php echo ctfw_format_phone( $phone ); // escaped, possibly linked ?>
						</li>
					<?php endif; ?>

					<?php if ($show_email) : ?>
						<li class="maranatha-location-short-email">
							<a href="mailto:<?php echo antispambot( $email, true ); ?>">
								<?php echo antispambot( $email ); // this on own line or validation can fail ?>
							</a>
						</li>
					<?php endif; ?>

					<?php if ($show_times) : ?>
						<li class="maranatha-locations-widget-entry-times maranatha-dark">
							<?php echo wptexturize( ctfw_one_line( $times ) ); ?>
						</li>
					<?php endif; ?>

				</ul>

			<?php endif; ?>

		</div>

		<?php if (get_the_excerpt() && ! empty( $instance['show_excerpt'] )): ?>

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
if (empty( $posts )) {

	?>
	<div>
		<?php _ex( 'There are no locations to show.', 'locations widget', 'maranatha' ); ?>
	</div>
	<?php

}

// HTML After
echo $args['after_widget'];
