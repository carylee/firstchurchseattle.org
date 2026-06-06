<?php
/**
 * Meta boxes and custom fields
 *
 * @package    Maranatha
 * @subpackage Admin
 * @copyright  Copyright (c) 2015, ChurchThemes.com
 * @link       https://churchthemes.com/themes/maranatha
 * @license    GPLv2 or later
 * @since      1.0
 */

/**********************************
 * OPTIONS META BOX
 **********************************/

/**
 * Options meta box
 *
 * This uses the CT_Meta_Box class.
 *
 * @since 1.0
 */
function maranatha_add_meta_box_options() {

	// Page templates required for "Banner on Related Content" to show and save
	// Not necessary for Gallery template in particular since setting it would unset Galleries template page.
	// The attached image for a Gallery template page will always show parent's banner, without this.
	$banner_related_page_templates = array( 'default' ); // allow for default, since setting can be used to show banner on sub pages
	$exclude_page_templates = array( 'gallery.php' );
	$content_types = ctfw_content_types();  // get page templates relating to content types (leaves out Homepage, for example)
	foreach ( $content_types as $content_type ) {
		foreach ( $content_type['page_templates'] as $content_type_page_template ) {
			if ( ! in_array( $content_type_page_template, $exclude_page_templates ) ) {
				$banner_related_page_templates[] = CTFW_THEME_PAGE_TPL_DIR . '/' . $content_type_page_template;
			}
		}
	}

	// Banner image opacity options
	$banner_opacity_options = array(
		/* translators: %s is Customizer header opacity value. '%%'' represents '%' (use two characters) */
		'' => sprintf(
			__( 'Customizer Setting (%s%%)', 'maranatha' ),
			ctfw_customization( 'header_image_opacity' )
		)
	);
	for ( $i = 1; $i <= 100; $i++ ) {
		$banner_opacity_options[$i] = sprintf( _x( '%d%%', 'banner image opacity', 'maranatha' ), $i );
	}

	// Show meta box on multiple post types
	$post_types = array(
		'post',
		'page',
		'ctc_sermon',
		'ctc_event',
		'ctc_location',
		'ctc_person',
	);

	// Loop post types
	foreach ( $post_types as $post_type ) {

		// Configure Meta Box
		$meta_box = array(

			// Meta Box
			'id' 		=> 'maranatha_options', // unique ID
			'title' 	=> __( 'Options', 'maranatha' ),
			'post_type'	=> $post_type,
			'context'	=> 'side', // where the meta box appear: normal (left above standard meta boxes), advanced (left below standard boxes), side
			'priority'	=> 'low', // high, core, default or low (see this: http://www.wproots.com/ultimate-guide-to-meta-boxes-in-wordpress/)
			'callback_args' => array(
				'__block_editor_compatible_meta_box' => true, // meta box works in Gutenberg editor.
			),

		);

		// Banner on Related Content
		if ( 'page' == $post_type ) {

			$meta_box['fields']['_ctcom_banner_related'] = array( // using _ctcom_ prefix for portability between themes
				'name'				=> __( 'Banner on Related Content', 'maranatha' ),
				'after_name'		=> '', // (Optional), (Required), etc.
				'desc'				=> __( "Example: Sermons template used. This page's banner shows on all sermon pages.", 'maranatha' ),
				'type'				=> 'checkbox', // text, textarea, checkbox, checkbox_multiple, radio, select, number, upload, upload_textarea, url
				'checkbox_label'	=> __( "Show banner on related content", 'maranatha' ), //show text after checkbox
				'options'			=> array(), // array of keys/values for radio or select
				'upload_button'		=> '', // text for button that opens media frame
				'upload_title'		=> '', // title appearing at top of media frame
				'upload_type'		=> '', // optional type of media to filter by (image, audio, video, application/pdf)
				'date_multiple'		=> false, // whether or not to allow date field type to select multiple dates, to be saved as comma-separated list.
				'date_button'       => '', // text for button user clicks to open datepicker calendar.
				'default'			=> '', // value to pre-populate option with (before first save or on reset)
				'no_empty'			=> false, // if user empties value, force default to be saved instead
				'allow_html'		=> false, // allow HTML to be used in the value (text, textarea)
				'attributes'		=> array(), // attr => value array (e.g. set min/max for number type)
				'class'				=> '', // class(es) to add to input (try try ctmb-medium, ctmb-small, ctmb-tiny)
				'field_attributes'	=> array(), // attr => value array for field container
				'field_class'		=> 'ctmb-no-top-margin', // class(es) to add to field container
				'custom_sanitize'	=> 'maranatha_sanitize_banner_related', // function to do additional sanitization
				'custom_field'		=> '', // function for custom display of field input
				//'page_templates'	=> $banner_related_page_templates, // field will not appear or save if one of these page templates are not selected
				// show for any post type so option available for Blog when set as "Posts Page"; otherwise this settings is lost
			);

		}

		// Banner Opacity Override
		$meta_box['fields']['_ctcom_banner_image_opacity'] = array( // using _ctcom_ prefix for portability between themes
			'name'				=> _x( 'Banner Image Opacity', 'meta box', 'maranatha' ),
			'after_name'		=> '', // (Optional), (Required), etc.
			'desc'				=> _x( 'Lower percentage makes background color show through. Aim for easy to read title.', 'banner custom field', 'maranatha' ),
			'type'				=> 'select', // text, textarea, checkbox, checkbox_multiple, radio, select, number, upload, upload_textarea, url
			'checkbox_label'	=> '', //show text after checkbox
			'options'			=> $banner_opacity_options, // array of keys/values for radio or select
			'upload_button'		=> '', // text for button that opens media frame
			'upload_title'		=> '', // title appearing at top of media frame
			'upload_type'		=> '', // optional type of media to filter by (image, audio, video, application/pdf)
			'date_multiple'		=> false, // whether or not to allow date field type to select multiple dates, to be saved as comma-separated list.
			'date_button'       => '', // text for button user clicks to open datepicker calendar.
			'default'			=> '', // value to pre-populate option with (before first save or on reset)
			'no_empty'			=> false, // if user empties value, force default to be saved instead
			'allow_html'		=> false, // allow HTML to be used in the value (text, textarea)
			'attributes'		=> array(), // attr => value array (e.g. set min/max for number type)
			'class'				=> '', // class(es) to add to input (try try ctmb-medium, ctmb-small, ctmb-tiny)
			'field_attributes'	=> array(), // attr => value array for field container
			'field_class'		=> '', // class(es) to add to field container
			'custom_sanitize'	=> '', // function to do additional sanitization
			'custom_field'		=> '', // function for custom display of field input
			'page_templates'	=> '', // field will not appear or save if one of these page templates are not selected
		);

		// Exclude from gallery
		$meta_box['fields']['_ctcom_gallery_exclude'] = array( // using _ctcom_ prefix for portability between themes
			'name'				=> _x( 'Gallery Content', 'meta box', 'maranatha' ),
			'after_name'		=> '', // (Optional), (Required), etc.
			'desc'				=> '',
			'type'				=> 'checkbox', // text, textarea, checkbox, checkbox_multiple, radio, select, number, upload, upload_textarea, url
			'checkbox_label'	=> __( 'Exclude gallery content from gallery widgets and templates', 'maranatha' ), //show text after checkbox
			'options'			=> array(), // array of keys/values for radio or select
			'upload_button'		=> '', // text for button that opens media frame
			'upload_title'		=> '', // title appearing at top of media frame
			'upload_type'		=> '', // optional type of media to filter by (image, audio, video, application/pdf)
			'date_multiple'		=> false, // whether or not to allow date field type to select multiple dates, to be saved as comma-separated list.
			'date_button'       => '', // text for button user clicks to open datepicker calendar.
			'default'			=> false, // value to pre-populate option with (before first save or on reset)
			'no_empty'			=> false, // if user empties value, force default to be saved instead
			'allow_html'		=> false, // allow HTML to be used in the value (text, textarea)
			'attributes'		=> array(), // attr => value array (e.g. set min/max for number type)
			'class'				=> '', // class(es) to add to input (try try ctmb-medium, ctmb-small, ctmb-tiny)
			'field_attributes'	=> array(), // attr => value array for field container
			'field_class'		=> '', // class(es) to add to field container
			'custom_sanitize'	=> '', // function to do additional sanitization
			'custom_field'		=> '', // function for custom display of field input
			'page_templates'	=> array(), // field will not appear or save if one of these page templates are not selected
		);

		// Add Meta Box
		new CT_Meta_Box( $meta_box );

	}

}

add_action( 'admin_init', 'maranatha_add_meta_box_options' );

/**********************************
 * SANITIZATION
 **********************************/

/**
 * Banner Related Sanitization
 *
 * This callback runs after CT_Meta_Box general sanitization but before saving.
 *
 * If another page of same content type (based on page template) has this set, that
 * page will have this value wiped. There should be only one Banner per section.
 *
 * For example, if there are two pages using the Upcoming and Past Events templates,
 * only one can have _ctcom_banner_related set. They cannot both provide their
 * banner to all the event posts.
 *
 * @since 1.0
 * @param mixed $value Input to sanitize before saving
 * @return mixed Sanitized value
 */

function maranatha_sanitize_banner_related( $value ) {

	global $post_id, $post;

	// Is box checked?
	if ( ! empty( $value ) ) {

		// Get page's template
		$page_template = get_post_meta( $post_id, '_wp_page_template', true );

		// Is it custom, not Default?
		if ( ! empty( $page_template ) && 'default' != $page_template ) {

			// What content type does page template belong to?
			$content_type = ctfw_content_type_by_page_template( $page_template );

			// Have content type
			if ( $content_type ) {

				// Get all page templates belonging to content type
				$content_type_page_templates = ctfw_content_type_data( $content_type, 'page_templates' );

				// Found page templates
				if ( ! empty( $content_type_page_templates ) ) {

					// Get all pages having _ctcom_banner_related set
					$banner_pages = maranatha_banner_related_pages();

					// Loop pages
					foreach ( $banner_pages as $page ) {

						// Exclude self
						if ( $post_id == $page->ID ) {
							continue;
						}

						// Get page's template
						$other_page_template = basename( get_post_meta( $page->ID, '_wp_page_template', true ) );

						// Uses one of the content type's templates?
						if ( in_array( $other_page_template, $content_type_page_templates ) ) {

							// Wipe _ctcom_banner_related on this other page
							update_post_meta( $page->ID, '_ctcom_banner_related', '' );

						}

					}

				}

			}

		}

	}

	// Return value for saving
	return $value;

}

