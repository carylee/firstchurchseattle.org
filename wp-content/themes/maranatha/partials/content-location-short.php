<?php
/**
 * Short Location Content (Archive)
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

// Get data
// $address, $show_directions_link, $directions_url, $phone, $email, $times, $map_lat, $map_lng, $map_has_coordinates, $map_type, $map_zoom
extract( ctfw_location_data() );

?>

<article id="post-<?php the_ID(); ?>" <?php maranatha_short_post_classes( 'maranatha-location-short' ); ?>>

	<header class="maranatha-entry-short-header">

		<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-header-short' ); // show title and image ?>

		<ul class="maranatha-entry-meta maranatha-entry-short-meta">

			<?php if ($address) : ?>

				<li class="maranatha-location-short-address maranatha-dark">
					<?php echo ctfw_address_one_line( $address ); ?>
				</li>

			<?php endif; ?>

			<?php if ($phone) : ?>

				<li class="maranatha-location-short-phone maranatha-dark">
					<?php echo ctfw_format_phone( $phone ); // escaped, possibly linked ?>
				</li>

			<?php endif; ?>

			<?php if ($email) : ?>

				<li class="maranatha-location-short-email">

					<a href="mailto:<?php echo antispambot( $email, true ); ?>">
						<?php echo antispambot( $email ); // this on own line or validation can fail ?>
					</a>

				</li>

			<?php endif; ?>

		</ul>

	</header>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-excerpt' ); // show excerpt if no image ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-short' ); // show appropriate button(s) ?>

</article>
