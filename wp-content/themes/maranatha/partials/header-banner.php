<?php
/**
 * Header Banner
 *
 * Outputs an image overlayed by a title of the current section based on content type.
 * Pages can use the "Banner" meta box to control how and where this is shown.
 *
 * If no page/post featured image is provided, the Customizer's Header Image is used.
 *
 * This is loaded by header.php.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Not on homepage
if ( is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/homepage.php' ) ) {
	return;
}

// Get banner data pertaining to related content
// This helps know which image to use (ie. use Sermons template's image on
// an individual sermons when it doesn't have its own)
$banner = maranatha_banner_data();

// Image data
$image_url = '';
$image_opacity = '';

// Get Customizer's Header Image in case needed as default
$default_image_url = get_header_image();

// Use featured image for page or post if available
// This might be the post's own image or one from a related page
if ( $banner['page'] ) { // banner page found

	// Get banner image URL from post's Featured Image
	$image_src = wp_get_attachment_image_src( get_post_thumbnail_id( $banner['page']->ID ), 'maranatha-banner' );
	$image_url = ! empty( $image_src[0] ) ? $image_src[0] : false;

	// Get opacity and prepare styles for image div
	if ( $image_url ) {
		$image_opacity = get_post_meta( $banner['page']->ID, '_ctcom_banner_image_opacity', true );
	}

}

// Use default Header Image since no featured image found
if ( ! $image_url && $default_image_url ) {

	// Get banner image URL from post's Featured Image
	$image_url = $default_image_url;

	// Get opacity from Customizer for default image
	$image_opacity = ctfw_customization( 'header_image_opacity' );

}

// Prepare styles for image overlay
$image_style = '';
if ( $image_url ) {
	$image_opacity_decimal = ( ! empty( $image_opacity ) ? $image_opacity : ctfw_customization( 'header_image_opacity' ) ) / 100;
	$image_style = "opacity: $image_opacity_decimal; background-image: url($image_url);";
}

?>

<div id="maranatha-banner" class="maranatha-color-main-bg">

	<?php if ( $image_style ) : // have image to show ?>
		<div id="maranatha-banner-image" style="<?php echo esc_attr( $image_style ); ?>"></div>
	<?php endif; ?>

	<div id="maranatha-banner-inner" class="maranatha-centered-large">

		<div id="maranatha-banner-title">
			<?php /* Using div instead of H1, because H1 is in <article> as hidden assistive text for proper HTMl5 and Outline */ ?>
			<div class="maranatha-h1"><?php maranatha_title_paged(); ?></div>
		</div>

	</div>

</div>
