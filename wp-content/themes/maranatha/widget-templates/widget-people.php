<?php
/**
 * People Widget Template
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

	// Get people meta data
	// $position, $phone, $email, $urls
	extract( ctfw_person_data() );

	// Show content
	$show_image = $instance['show_image'] && has_post_thumbnail() ? true : false;
	$show_position = $instance['show_position'] && $position ? true : false;
	$show_phone = $instance['show_phone'] && $phone ? true : false;
	$show_email = $instance['show_email'] && $email ? true : false;
	$show_icons = $instance['show_icons'] && $urls ? true : false;
	$show_meta = ( $show_position || $show_phone || $show_email || $show_icons ) ? true : false;

	// Showing image?
	$showing_image_class = $show_image ? ' maranatha-widget-entry-showing-image' : '';

?>

	<article <?php maranatha_short_post_classes( 'maranatha-person-short' . $showing_image_class ); ?>>

		<div class="maranatha-entry-short-header maranatha-clearfix">

			<?php if ($show_image) : ?>

				<div class="maranatha-entry-short-image maranatha-hover-image">
					<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
						<?php the_post_thumbnail( 'maranatha-thumb-small' ); ?>
					</a>
				</div>

			<?php endif; ?>

			<?php if (ctfw_has_title()) : ?>

				<h3>
					<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
				</h3>

			<?php endif; ?>

			<?php if ($show_meta) : ?>

				<ul class="maranatha-entry-meta maranatha-entry-short-meta">

					<?php if ($show_position) : ?>
						<li class="maranatha-person-short-position maranatha-dark">
							<?php echo esc_html( wptexturize( $position ) ); ?>
						</li>
					<?php endif; ?>

					<?php if ($show_phone) : ?>
						<li class="maranatha-person-short-phone maranatha-dark">
							<?php echo ctfw_format_phone( $phone ); // escaped, possibly linked ?>
						</li>
					<?php endif; ?>

					<?php if ($show_email) : ?>
						<li class="maranatha-person-short-email maranatha-dark">
							<a href="mailto:<?php echo antispambot( $email, true ); ?>">
								<?php echo antispambot( $email ); // this on own line or validation can fail ?>
							</a>
						</li>
					<?php endif; ?>

					<?php if ($show_icons) : ?>
						<li class="maranatha-person-short-icons maranatha-widget-entry-icons">
							<?php maranatha_social_icons( $urls ); ?>
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
		<?php _ex( 'There are no people to show.', 'people widget', 'maranatha' ); ?>
	</div>
	<?php

}

// HTML After
echo $args['after_widget'];
