<?php
/**
 * Navigation
 *
 * Functions to help with navigational.
 *
 * @package    Maranatha
 * @subpackage Functions
 * @copyright  Copyright (c) 2015, ChurchThemes.com
 * @link       https://churchthemes.com/themes/maranatha
 * @license    GPLv2 or later
 * @since      1.0
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/**********************************
 * CUSTOM MENUS
 **********************************/

/**
 * Register header and footer menu locations
 *
 * @since 1.0
 */
function maranatha_register_menus() {

	// Register header menu location (main menu with dropdowns)
	register_nav_menu( 'header', _x( 'Header', 'menu location', 'maranatha' ) );

	// No footer menu. Header menu is sticky; always in view.
}

add_action( 'init', 'maranatha_register_menus' );

/********************************
 * BREADCRUMBS
 ********************************/

/**
 * Output breadcrumb path
 *
 * @since 1.0
 */
function maranatha_breadcrumbs() {

	// Build them with framework
	$breadcrumbs = new CTFW_Breadcrumbs( array(
		'classes'	=> '', // center the breadcrumbs like content
		/* translators: separator between breadcrumb path links */
		'separator'	=> ' <span class="maranatha-breadcrumb-separator">' . __( '/', 'maranatha' ) . '</span> ',
		'shorten'	=> 20, // default is 30 (make some more room for archive dropdowns on right)
	) );

	// Return filtered
	return apply_filters( 'maranatha_breadcrumbs', $breadcrumbs );

}

/********************************
 * PREVIOUS / NEXT
 ********************************/

/**
 * Single post navigation data
 *
 * Prepare various data for rendering a Previous or Next nav block.
 *
 * @since 1.0
 * @param string $direction previous or next
 * @param object $post Post object
 * @return array Array with 'url', 'label' and 'image_style' style attribute value
 */
function maranatha_single_post_nav_data( $direction, $post ) {

	$data = array();

	// URL
	$data['url'] = get_permalink( $post );

	// Image
	$data['image_style'] = '';
	if ( 'ctc_person' != get_post_type() ) { // Hide image on people (eyes not centered, heads are cropped)

		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'post-thumbnail' );
		$image_url = ! empty( $image[0] ) ? $image[0] : '';

		if ( $image_url ) {
			$image_opacity = get_post_meta( '_ctcom_header_banner_opacity', true );
			$image_opacity_decimal = ( ! empty( $image_opacity ) ? $image_opacity : ctfw_customization( 'header_image_opacity' ) ) / 100;
			$data['image_style'] = "opacity: $image_opacity_decimal; background-image: url($image_url);";
		}

	}

	// Label
	// Previous or Next, or Person's position, or nothing for Location
	$data['label'] = maranatha_single_post_nav_label( $direction, $post );

	// Return filtered
	return apply_filters( 'maranatha_single_post_nav_data', $data, $direction, $post );

}

/**
 * Single post navigation label
 *
 * Label to show in single post nav block above the post title.
 *
 * Show Previous or Next for all post types but Location and Person.
 * Show person's Position instead of Previous or Next
 * Show nothing for Location
 *
 * Users have different idea of what Previous and Next mean on non-dated posts,
 * so it's better not to use that terminology.
 *
 * @since 1.0
 * @param string $direction previous or next
 * @param object $post Post object
 * @return string Label to show on nav control
 */
function maranatha_single_post_nav_label( $direction, $post ) {

	$label = '';

	// Not a Location (no label)
	if ( 'ctc_location' != $post->post_type ) {

		// Person, use Position
		if ( 'ctc_person' == $post->post_type ) {

			$position = get_post_meta( $post->ID, '_ctc_person_position', true );
			$position = trim( $position );

			if ( $position ) {
				$label = $position;
			}

		}

		// Use Previous/Next
		if ( ! $label ) {

			if ( 'previous' == $direction ) {
				$label = _x( 'Previous', 'single post nav', 'maranatha' );
			} else {
				$label = _x( 'Next', 'single post nav', 'maranatha' );
			}

		}

	}

	return apply_filters( 'maranatha_single_post_nav_label', $label, $direction, $post );

}

