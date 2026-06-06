<?php
/**
 * Theme Footer
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Globals
global $maranatha_home_section_num ;

// Footer icons
$footer_icons = maranatha_social_icons( ctfw_customization( 'footer_icon_urls' ), 'return' );

// Get first location post
$location = ctfw_first_ordered_post( 'ctc_location' );

// Get locations data, if showing location
$location_count = 0;
$locations_page = ctfw_get_page_by_template( 'locations.php' );
if ( ctfw_customization( 'show_footer_location' ) && ! empty( $location['ID'] ) ) {

	// Meta data for page
	extract( ctfw_location_data( $location['ID'] ) );

	// Get Locations page and count
	$location_counts = wp_count_posts( 'ctc_location' );
	$location_count = isset( $location_counts->publish ) ? $location_counts->publish : 0;

}

// Showing a map?
$has_map = false;
if ( ! empty( $map_lat ) && ! empty( $map_lng ) ) {
	$has_map = true;
}

// Notice / Copyright
$footer_notice = ctfw_customization( 'footer_notice' );

// Classes
$classes = array();

	// Location
	if ( $location_count ) {
		$classes[] = 'maranatha-footer-has-location';
	} else {
		$classes[] = 'maranatha-footer-no-location';
	}

	// Location Map
	if ( $has_map ) {
		$classes[] = 'maranatha-footer-has-map';
	} else {
		$classes[] = 'maranatha-footer-no-map';
	}

	// Social Icons
	if ( $footer_icons ) {
		$classes[] = 'maranatha-footer-has-icons';
	} else {
		$classes[] = 'maranatha-footer-no-icons';
	}

	// Notice
	if ( $footer_notice ) {
		$classes[] = 'maranatha-footer-has-notice';
	} else {
		$classes[] = 'maranatha-footer-no-notice';
	}

	$classes = implode( ' ', $classes );
	if ( $classes ) {
		$class_attr = ' class="' . esc_attr( $classes ) . '"';
	}

?>

<footer id="maranatha-footer"<?php echo $class_attr; ?>>

	<?php get_sidebar( 'footer' ); ?>

	<?php
	// Load map section (also used on homepage)
	get_template_part( CTFW_THEME_PARTIAL_DIR . '/map-section' );
	?>

	<?php if ( $footer_icons || $footer_notice ) : ?>

		<div id="maranatha-footer-icons-notice" class="maranatha-color-main-bg">

			<?php if ( $footer_icons ) : ?>

				<div id="maranatha-footer-icons" class="maranatha-centered-large">
					<?php echo $footer_icons; ?>
				</div>

			<?php endif; ?>

			<?php if ( $footer_notice ) : ?>

				<div id="maranatha-footer-notice">
					<?php echo nl2br( wptexturize( do_shortcode( $footer_notice ) ) ); ?>
				</div>

			<?php endif; ?>

		</div>

	<?php endif; ?>

</footer>

<?php
// Show latest events, comments link, etc. fixed to bottom of screen
get_template_part( CTFW_THEME_PARTIAL_DIR . '/footer-stickies' );
?>

<?php
wp_footer(); // a hook for extra code in the footer, if needed
?>

</body>
</html>