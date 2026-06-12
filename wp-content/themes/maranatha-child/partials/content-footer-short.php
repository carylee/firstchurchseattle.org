<?php
/**
 * Short Content Footer
 *
 * Show appropriate button(s) beneath short display of post in loop.
 *
 * The ctc_sermon / ctc_location / ctc_event branches were removed once Church
 * Theme Content was decommissioned (2026-06-11): those post types are no longer
 * registered, so they can never surface in a query and the branches were dead.
 * ctc_person stays — firstchurch-people re-owns that type in place, so people
 * can still appear in generic loops (e.g. search results).
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

// Post type
$post_type = get_post_type();

?>

<footer class="maranatha-entry-short-footer">

	<?php
	// Person Buttons
	if ('ctc_person' == $post_type) :
	?>

		<?php if (fcs_has_content()) : // show only if has bio content ?>

			<ul class="maranatha-entry-short-footer-item maranatha-buttons-list">

				<li>

					<a href="<?php the_permalink(); ?>">
						<?php echo wptexturize( __( "View Profile", 'maranatha' ) ); ?>
					</a>

				</li>

			</ul>

		<?php endif; ?>

	<?php
	// Gallery Page Button
	elseif ('page' == $post_type && isset( $post->page_template ) && 'page-templates/gallery.php' == $post->page_template) :
	?>

		<ul class="maranatha-entry-short-footer-item maranatha-buttons-list">

			<li>

				<a href="<?php the_permalink(); ?>">
					<span class="<?php fcs_icon_class( 'gallery' ); ?>"></span>
					<?php _e( 'View Gallery', 'maranatha' ); ?>
				</a>

			</li>

		</ul>

	<?php
	// Generic Post Type Button
	else :

		$post_type_obj = get_post_type_object( $post->post_type );

	?>

		<div class="maranatha-entry-short-footer-item">

			<ul class="maranatha-entry-short-footer-item maranatha-buttons-list">

				<li>

					<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">

						<?php
						/* translators: %s is post type name */
						printf( __( 'View %s', 'maranatha' ), $post_type_obj->labels->singular_name );
						?>

					</a>

				</li>

			</ul>

		</div>

	<?php endif; ?>

</footer>
