<?php
/**
 * Full Location Content (Single)
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

// Get data
// $address, $show_directions_link, $directions_url, $phone, $email, $times, $map_lat, $map_lng, $map_has_coordinates, $map_type, $map_zoom
extract( ctfw_location_data() );

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'maranatha-entry-full maranatha-location-full' ); ?>>

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

		<?php if ($times || $phone || $email || ( ! $map_has_coordinates && $address )) : ?>

			<ul class="maranatha-entry-meta maranatha-entry-full-meta">

				<?php
				// No map so show Address
				if (! $map_has_coordinates && $address) :
				?>

					<li id="maranatha-location-address">

						<div class="maranatha-entry-full-meta-label"><?php _e( 'Address', 'maranatha' ); ?></div>

						<?php echo nl2br( esc_html( wptexturize( $address ) ) ); ?>

						<?php if ($directions_url) : ?>

							<div class="maranatha-entry-full-meta-second-line">
								<a href="<?php echo esc_url( $directions_url ); ?>" class="maranatha-map-button-directions" target="_blank" rel="noopener noreferrer"><?php _e( 'Directions', 'maranatha' ); ?></a>
							</div>

						<?php endif; ?>

					</li>

				<?php endif; ?>

				<?php if ($times) : ?>

					<li id="maranatha-location-time">

						<div class="maranatha-entry-full-meta-label"><?php _ex( 'Time', 'location', 'maranatha' ); ?></div>

						<div class="maranatha-dark"><?php echo nl2br( esc_html( wptexturize( $times ) ) ); ?></div>

					</li>

				<?php endif; ?>

				<?php if ($phone) : ?>

					<li id="maranatha-location-phone">

						<div class="maranatha-entry-full-meta-label"><?php _e( 'Phone', 'maranatha' ); ?></div>

						<div class="maranatha-dark">
							<?php echo ctfw_format_phone( $phone ); // escaped, possibly linked ?>
						</div>

					</li>

				<?php endif; ?>

				<?php if ($email) : ?>

					<li id="maranatha-location-email">

						<div class="maranatha-entry-full-meta-label"><?php _e( 'Email', 'maranatha' ); ?></div>

						<a href="mailto:<?php echo antispambot( $email, true ); ?>">
							<?php echo antispambot( $email ); // this on own line or validation can fail ?>
						</a>

					</li>

				<?php endif; ?>

			</ul>

		<?php endif; ?>

	</header>

	<?php if (ctfw_has_content()) : // might not be any content, so let header sit flush with bottom ?>

		<div id="maranatha-location-content" class="maranatha-entry-content maranatha-entry-full-content maranatha-centered-small">

			<?php the_content(); ?>

			<?php do_action( 'maranatha_after_content' ); ?>

		</div>

	<?php endif; ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-full' ); // multipage nav, term lists, "Edit" button, etc. ?>

</article>
