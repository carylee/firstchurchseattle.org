<?php
/**
 * Map Section
 *
 * This shows on homepage after first section, on footer of other pages and at top of an event or location.
 * It will also show in the footer if not used after first section on homepage or not on an event or location.
 *
 * Use get_template_part to load this (see homepage.php, footer.php, partials/content-event.php, partials/content-location.php).
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

// Don't attempt again in footer if was shown at top of page already
if (! empty( $GLOBALS['maranatha_top_map_shown'] )) {
	return;
}

// No map yet
$has_map = false;
$placement = '';

// Top of page
if (empty( $GLOBALS['maranatha_top_map_attempted'] )) {

	// Homepage
	if (is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/homepage.php' )) {

		// Showing on top was attempted
		$GLOBALS['maranatha_top_map_attempted'] = true;

		// Show if enabled in Customizer
		if (ctfw_customization( 'show_home_location' )) {

			// Don't show map a second time in footer if shown further up at top
			$GLOBALS['maranatha_top_map_shown'] = true;

			$placement = 'homepage';
			$container_tag = 'section';
			$canvas_class = 'maranatha-viewport-height-half';
			$container_class = "$canvas_class maranatha-home-section maranatha-map-section";
			$before = '';
			$after = '';

		} else {
			return;
		}

	}

	// Event
	if (is_singular( 'ctc_event' )) {

		// Showing on top was attempted
		$GLOBALS['maranatha_top_map_attempted'] = true;

		// Get data
		$data = ctfw_event_data();

		// Show if have coordinates
		if ($data['map_lat'] && $data['map_lng']) {

			$has_map = true;

			// Don't show map a second time in footer if shown further up at top
			$GLOBALS['maranatha_top_map_shown'] = true;

			$placement = 'event';
			$container_tag = 'div';
			$canvas_class = 'maranatha-viewport-height-half';
			$container_class = "$canvas_class maranatha-map-section";
			$before = '<div class="maranatha-entry-full-map">';
			$after = '</div>';

		} else {
			return;
		}

	}

	// Location
	if (is_singular( 'ctc_location' )) {

		// Showing on top was attempted
		$GLOBALS['maranatha_top_map_attempted'] = true;

		// Get data
		$data = ctfw_location_data();

		// Show if have coordinates
		if ($data['map_lat'] && $data['map_lng']) {

			$has_map = true;

			// Don't show map a second time in footer if shown further up at top
			$GLOBALS['maranatha_top_map_shown'] = true;

			$placement = 'event';
			$container_tag = 'div';
			$canvas_class = 'maranatha-viewport-height-half';
			$container_class = "$canvas_class maranatha-map-section";
			$before = '<div class="maranatha-entry-full-map">';
			$after = '</div>';

		} else {
			return;
		}

	}

}

// Get location data for Homepage or Footer
// $data has aleady been gotten if is event or location single view
if (! in_array( $placement, array( 'event', 'location' ) )) {

	// Get first location post
	$location = ctfw_first_ordered_post( 'ctc_location' );

	// Get locations data
	$location_count = 0;
	if (! empty( $location['ID'] )) {

		// Meta data for first location
		$data = ctfw_location_data( $location['ID'] );

		// Has coordinates for map?
		if (! empty( $data['map_lat'] ) && ! empty( $data['map_lng'] )) {

			$has_map = true;

			// Get locations page
			$locations_page = ctfw_get_page_by_template( 'locations.php' );

			// Locations page URL
			$locations_page_url = '';
			if (isset( $locations_page->ID )) {
				$locations_page_url = get_permalink( $locations_page->ID );
			}

			// Count locations
			$location_counts = wp_count_posts( 'ctc_location' );
			$location_count = isset( $location_counts->publish ) ? $location_counts->publish : 0;

		}

		// URL for single location or all locations page, if have multiple, and page exists
		$locations_page_ready = false;
		$single_or_multiple_locations_url = get_permalink( $location['ID'] );
		if ($location_count > 1 && $locations_page_url) {
			$locations_page_ready = true;
			$single_or_multiple_locations_url = $locations_page_url;
		}

	}

}

// Footer?
if (empty( $placement ) && ctfw_customization( 'show_footer_location' )) {
	$placement = 'footer';
	$container_tag = 'div';
	$canvas_class = 'maranatha-viewport-height-half';
	$container_class = "$canvas_class maranatha-map-section";
	$before = '';
	$after = '';
}

// Nothing to show
if (! $has_map || ! $placement) {
	return;
}

// Header or Footer
$header_or_footer = in_array( $placement, array( 'homepage', 'footer' ) ) ? true : false;

// Show buttons
$show_buttons = $header_or_footer || ! empty( $data['directions_url'] ) || ! empty( $locations_page_ready ) ? true : false;

// Show phone?
$show_phone = false;
if (! empty( $data['phone'] ) && $header_or_footer) { // don't show phone on Location (it shows in meta)
	$show_phone = true;
}

// Show email?
$show_email = false;
if (! empty( $data['email'] ) && $header_or_footer) { // don't show email on Location (it shows in meta)
	$show_email = true;
}

// Show times?
$show_times = false;
if (! empty( $data['times'] ) && $header_or_footer) { // don't show times on Location (it shows in meta)
	$show_times = true;
}

?>

<?php if ($before) echo $before; ?>

<<?php echo $container_tag; ?> class="<?php echo $container_class; ?>">

	<?php

	// Use Google Maps JavaScript API
	echo ctfw_google_map( array(
		'latitude'			=> $data['map_lat'],
		'longitude'			=> $data['map_lng'],
		'type'				=> $data['map_type'],
		'zoom'				=> $data['map_zoom'],
		'container'			=> false, // no container wrapping the map canvas
		'canvas_id'			=> 'maranatha-map-section-canvas', // custom map canvas element ID (default ctfw-google-map-##### random)
		'canvas_class'		=> $canvas_class, // add class
		'responsive'		=> false, // false removes .ctfw-google-map-responsive
		'marker'			=> false, // no marker, adding custom overlay
		'center_resize'		=> false, // no centering after resize
		'callback_loaded'	=> 'maranatha_position_map_section', // see main.js function
		'callback_resize'	=> 'maranatha_position_map_section', // see main.js function
	) );

	?>

	<?php if ('section' == $container_tag) : ?>
		<h2 class="screen-reader-text"><?php _ex( 'Location Details', 'map section', 'maranatha' ); ?></h2>
	<?php endif; ?>

	<div id="maranatha-map-section-content-container">

		<div id="maranatha-map-section-content">

			<div id="maranatha-map-section-left">
				<span id="maranatha-map-section-marker" class="maranatha-circle-button maranatha-circle-button-small"><span class="el el-map-marker"></span></span>
			</div>

			<?php
			if (
				! empty( $data['venue'] )
				|| ! empty( $data['address'] )
				|| $show_times
				|| $show_phone
				|| $show_email
				|| $show_buttons
			) :
			?>

				<div id="maranatha-map-section-right">

					<div id="maranatha-map-section-info">

						<ul id="maranatha-map-section-info-list" class="maranatha-clearfix">

							<?php if (! empty( $data['address'] )) : ?>

								<li id="maranatha-map-section-address" class="maranatha-map-info-full">

								 	<span class="<?php maranatha_icon_class( 'map-address' ); ?>"></span>

									<p>
										<?php echo nl2br( esc_html( wptexturize( $data['address'] ) ) ); ?>
									</p>

								</li>

							<?php endif; ?>

							<?php if (! empty( $data['venue'] )) : ?>

								<li id="maranatha-map-section-venue" class="maranatha-map-info-full">

								 	<span class="<?php maranatha_icon_class( 'map-venue' ); ?>"></span>

									<p>
										<?php echo nl2br( esc_html( wptexturize( $data['venue'] ) ) ); ?>
									</p>

								</li>

							<?php endif; ?>

							<?php if ($show_times) : ?>

								<li id="maranatha-map-section-time" class="maranatha-map-info-full">

								 	<span class="<?php maranatha_icon_class( 'map-times' ); ?>"></span>

									<p>
										<?php echo nl2br( esc_html( wptexturize( $data['times'] ) ) ); ?>
									</p>

								</li>

							<?php endif; ?>

							<?php if ($show_phone) : ?>

								<li id="maranatha-map-section-phone">

								 	<span class="<?php maranatha_icon_class( 'map-phone' ); ?>"></span>

									<p>
										<?php echo ctfw_format_phone( $data['phone'] ); // escaped, possibly linked ?>
									</p>

								</li>

							<?php endif; ?>

							<?php if ($show_email) : ?>

								<li id="maranatha-map-section-email">

								 	<span class="<?php maranatha_icon_class( 'map-email' ); ?>"></span>

									<p>
										<a href="mailto:<?php echo antispambot( $data['email'], true ); ?>">
											<?php echo antispambot( $data['email'] ); // this on own line or validation can fail ?>
										</a>
									</p>

								</li>

							<?php endif; ?>

						</ul>

						<?php if ($show_buttons) : ?>

							<ul id="maranatha-map-section-buttons" class="maranatha-buttons-list maranatha-map-section-<?php echo ( empty( $location_count ) || 1 == $location_count ) ? 'single-location' : 'multiple-locations' ?>">

								<?php if ($header_or_footer) : ?>
									<li class="maranatha-map-button-more-item"><a href="<?php echo esc_url( $single_or_multiple_locations_url ); ?>" class="maranatha-map-button-more"><?php _ex( 'More Info', 'map section', 'maranatha' ); ?></a></li>
								<?php endif; ?>

								<?php if (! empty( $data['directions_url'] )) : ?>
									<li><a href="<?php echo esc_url( $data['directions_url'] ); ?>" class="maranatha-map-button-directions" target="_blank" rel="noopener noreferrer"><?php _e( 'Directions', 'maranatha' ); ?></a></li>
								<?php endif; ?>

								<?php if (! empty( $locations_page_ready )) : // show link if have Locations page and more than one location ?>
									<li><a href="<?php echo esc_url( $locations_page_url ); ?>" class="maranatha-map-button-locations"><?php _ex( 'Locations', 'map section', 'maranatha' ); ?></a></li>
								<?php endif; ?>

							</ul>

						<?php endif; ?>

					</div>

				</div>

			<?php endif; ?>

		</div>

	</div>

</<?php echo $container_tag; ?>>

<?php if ($after) echo $after; ?>
