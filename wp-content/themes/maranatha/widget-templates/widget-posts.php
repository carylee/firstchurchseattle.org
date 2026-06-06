<?php
/**
 * Posts Widget Template
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
$posts = $this->ctfw_get_posts(); // widget's default query according to field values

// Loop Posts
foreach ( $posts as $post ) : setup_postdata( $post );

	// Categories
	$categories = get_the_category_list(
		/* translators: used between list items, there is a space after the comma */
		__( ', ', 'maranatha' )
	);

?>

	<article <?php maranatha_short_post_classes(); ?>>

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


			<?php if ( $instance['show_date'] || $instance['show_author'] || ( $instance['show_category'] && $categories ) ) : ?>

				<ul class="maranatha-entry-meta maranatha-entry-short-meta">

					<?php if ( $instance['show_date'] ) : ?>
						<li class="maranatha-entry-short-date maranatha-dark">
							<time datetime="<?php echo esc_attr( the_time( 'c' ) ); ?>"><?php ctfw_post_date(); ?></time>
						</li>
					<?php endif; ?>

					<?php if ( $instance['show_author'] ) : ?>
						<li class="maranatha-entry-short-author">
							<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a>
						</li>
					<?php endif; ?>

					<?php if ( $instance['show_category'] && $categories ) : ?>
						<li class="maranatha-entry-short-category">
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
		<?php _ex( 'There are no posts to show.', 'posts widget', 'maranatha' ); ?>
	</div>
	<?php

}

// HTML After
echo $args['after_widget'];