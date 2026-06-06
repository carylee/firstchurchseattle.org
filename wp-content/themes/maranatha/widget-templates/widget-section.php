<?php
/**
 * Section Widget Template
 *
 * Produces output for appropriate widget class in framework.
 * $this, $instance (sanitized field values) and $args are available.
 *
 * Homepage map section is automatically shown after first section, if enabled (see bottom).
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

global $maranatha_home_section_num, $maranatha_home_section_last_has_image;

// Number each section
if ( ! isset( $maranatha_home_section_num ) ) {
	$maranatha_home_section_num = 0;
}
$maranatha_home_section_num++;

// First section?
$first_section = 1 == $maranatha_home_section_num ? true : false;

// Opacity
$opacity_decimal = ( ! empty( $instance['image_opacity'] ) ? $instance['image_opacity'] : ctfw_customization( 'header_image_opacity' ) ) / 100;
$opacity_decimal_image = $opacity_decimal;
$opacity_decimal_video = 1 - $opacity_decimal; // 0.9 instead of 0.1 when laid over video

// Image
$image = wp_get_attachment_image_src( $instance['image_id'], 'maranatha-section' );
$image_url = ! empty( $image[0] ) ? $image[0] : '';

// Video
// First section only. Poor performance/overwhelming on multiple.
// Multiple videos also create z-index issues in non-webkit browsers (FF, Edge, IE)
// Video opacity is 100% and color goes on top because of opacity big w/Chrome on Mac: http://stackoverflow.com/questions/40349678/video-with-css-opacity-property-appears-unusually-dim-on-chrome-54-for-mac-onl
$video_url = $first_section && ! empty( $instance['video'] ) ? $instance['video'] : '';
if ( $video_url ) {

	// Is it MP4?
	$video_url_no_qs = strtok( $video_url, '?' ); // check without query string, so Dropbox ?raw=1 can be used.
	$video_url_data = wp_check_filetype( $video_url_no_qs );
	if ( 'mp4' == $video_url_data['ext'] ) { // these are same if .mp4 was not removed

		// Enqueue Vide JS only when a widget uses video on homepage
		wp_enqueue_script( 'jquery-vide', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.vide.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update

	}

	// Unset video URL since not MP4
	else {
		$video_url = '';
	}

}

// Image or Video
$maranatha_home_section_last_has_image = false; // footer uses this
if ( $image_url || $video_url ) {
	$maranatha_home_section_last_has_image = true; // image or video
}

// Heading tag
$heading_tag = 'h1';
if ( $maranatha_home_section_num > 1 ) {
	$heading_tag = 'h2';
}

?>

<section id="maranatha-home-section-<?php echo $maranatha_home_section_num; ?>"<?php

	$li_classes = array();

	// Main classes
	$li_classes[] = 'maranatha-home-section';
	$li_classes[] = 'maranatha-viewport-height';

	// Color class
	if ( $first_section ) { // first section is Main color
		$li_classes[] = 'maranatha-color-main-bg';
	} else if ( $maranatha_home_section_num % 2 == 0 ) { // even is Dark
		$li_classes[] = 'maranatha-color-dark-bg';
	} else { // odd is Light
		$li_classes[] = 'maranatha-color-light-bg';
	}

	// Title
	if ( $instance['title'] ) {
		$li_classes[] = 'maranatha-section-has-title';
	} else {
		$li_classes[] = 'maranatha-section-no-title';
	}

	// Content
	if ( $instance['content'] ) {
		$li_classes[] = 'maranatha-section-has-content';
	} else {
		$li_classes[] = 'maranatha-section-no-content';
	}

	// Image
	if ( $image_url ) {
		$li_classes[] = 'maranatha-section-has-image';
	} else {
		$li_classes[] = 'maranatha-section-no-image';
	}

	// Video
	if ( $video_url ) {
		$li_classes[] = 'maranatha-section-has-video';
	} else {
		$li_classes[] = 'maranatha-section-no-video';
	}

	// Image or Video
	if ( $image_url || $video_url ) {
		$li_classes[] = 'maranatha-section-has-image-or-video';
	} elseif ( ! $image_url && ! $video_url ) {
		$li_classes[] = 'maranatha-section-no-image-or-video';
	}

	// Output classes
	if ( ! empty( $li_classes ) ) {
		echo ' class="' . implode( ' ', $li_classes ). '"';
	}

?>>

	<?php if ( ! $first_section ) : // tint for images ?>
		<div class="maranatha-home-section-color maranatha-color-main-bg"></div>
	<?php endif; ?>

	<?php if ( $image_url ) : ?>
		<div class="maranatha-home-section-image" style="<?php echo esc_attr( "opacity: $opacity_decimal_image; background-image: url($image_url);" ); ?>"></div> <!-- faster than :before on FF/Retina -->
	<?php endif; ?>

	<?php if ( $video_url ) : ?>

		<div id="maranatha-home-section-video">

			<div id="maranatha-home-section-video-vide" data-video-url="<?php echo esc_url( $video_url ); ?>"></div>

			<div id="maranatha-home-section-video-color" class="maranatha-color-main-bg" style="<?php echo esc_attr( "opacity: $opacity_decimal_video;" ); ?>"></div>

		</div>

	<?php endif; ?>

	<div class="maranatha-home-section-inner">

		<div class="maranatha-home-section-content">

			<?php if ( $instance['title'] ) : // title provided ?>

				<<?php echo $heading_tag; ?>>
					<?php echo esc_html( $instance['title'] ); ?>
				</<?php echo $heading_tag; ?>>

			<?php endif; ?>

			<?php if ( $instance['content'] ) : // content provided ?>
				<?php echo do_shortcode( wpautop( wptexturize( force_balance_tags( $instance['content'] ) ) ) ); ?>
			<?php endif; ?>

			<?php
			$links = $this->ctfw_links();
			if ( $links ) :
			?>

				<ul class="maranatha-circle-buttons-list">

					<?php foreach( $links as $link ) : ?>
						<li><a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['text'] ); ?></a></li>
					<?php endforeach; ?>

				</ul>

			<?php endif; ?>

		</div>

		<?php if ( $first_section ) : ?>
			<span title="Scroll Down" id="maranatha-home-header-arrow" class="el el-chevron-down"></span>
		<?php endif; ?>

	</div>

</section>

<?php if ( $first_section ) : ?>

	<?php
	// Load map section (also used in footer on sub-pages)
	// Don't check has_map, needs to set a global
	get_template_part( CTFW_THEME_PARTIAL_DIR . '/map-section' );
	?>

<?php endif; ?>
