<?php
/**
 * Theme Customizer
 *
 * Add options to the Theme Customizer.
 *
 * @package    Maranatha
 * @subpackage Functions
 * @copyright  Copyright (c) 2015 - 2017, ChurchThemes.com
 * @link       https://churchthemes.com/themes/maranatha
 * @license    GPLv2 or later
 * @since      1.0
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/*********************************************
 * CHOICES
 *********************************************/

/**
 * Logo Type Choices
 *
 * @since 1.0
 * @return array Choices for user selection and sanitization
 */
function maranatha_customize_logo_type_choices() {

	$choices = 	array(
		'image' => _x( 'Image', 'customizer', 'maranatha' ),
		'text'	=> _x( 'Text', 'customizer', 'maranatha' ),
	);

	return apply_filters( 'maranatha_customize_logo_type_choices', $choices );

}

/**
 * Logo Text Size Choices
 *
 * @since 1.0
 * @return array Choices for user selection and sanitization
 */
function maranatha_customize_logo_text_size_choices() {

	$choices = 	array(
		'extra-small'	=> _x( 'Extra Small', 'customizer', 'maranatha' ),
		'small'			=> _x( 'Small', 'customizer', 'maranatha' ),
		'medium'		=> _x( 'Medium', 'customizer', 'maranatha' ),
		'large'			=> _x( 'Large', 'customizer', 'maranatha' ),
		'extra-large'	=> _x( 'Extra Large', 'customizer', 'maranatha' ),
	);

	return apply_filters( 'maranatha_customize_logo_text_size_choices', $choices );

}

/**
 * Bottom Left Sticky Choices
 *
 * @since 1.0
 * @return array Choices for user selection and sanitization
 */
function maranatha_customize_bottom_left_sticky_choices() {

	$choices = array(
		'events'	=> _x( 'Upcoming Events', 'customizer', 'maranatha' ),
		'sermons'	=> sprintf(
			/* translators: %1$s is "Sermons", possibly translated or changed by settings */
			_x( 'Latest %1$s', 'customizer', 'maranatha' ),
			ctfw_sermon_word_plural()
		),
		'posts'		=> _x( 'Latest Posts', 'customizer', 'maranatha' ),
		'content'	=> _x( 'Custom Content', 'customizer', 'maranatha' ),
		'none'		=> _x( 'Nothing', 'customizer', 'maranatha' )
	);

	return apply_filters( 'maranatha_customize_bottom_left_sticky_choices', $choices );

}

/**
 * Bottom Left Sticky Items Limit Choices
 *
 * @since 1.0
 * @return array Choices for user selection and sanitization
 */
function maranatha_customize_bottom_left_sticky_items_limit_choices() {

	$choices = array(
		'1'	=> _x( 'One', 'customizer header items', 'maranatha' ),
		'2'	=> _x( 'Two', 'customizer header items', 'maranatha' ),
	);

	return apply_filters( 'maranatha_customize_bottom_left_sticky_items_limit_choices', $choices );

}

/*********************************************
 * SETTINGS
 *********************************************/

/**
 * Sections, settings and controls
 *
 * @since 1.0
 * @param object $wp_customize WordPress theme customizer object
 */
function maranatha_customize_register( $wp_customize ) {

	// Master Option
	// All options will be saved as an array under this single option ID
	$option_id = ctfw_customize_option_id();
	$setting_type = 'option';

	// Default values
	$defaults = ctfw_customize_defaults();

	// Section and control priority
	$section_priority = 0; // start it off
	$section_increment = 20;
	$control_increment = 20;

	/*---------------------------------------------
	 * Site Identity (Core)
	 *--------------------------------------------*/

	// Add description for Site Identity
	$section = 'title_tagline';
	$wp_customize->get_section( $section )->priority = $section_priority += $section_increment; // section order
	$wp_customize->get_section( $section )->description = __( 'Site Title and Tagline are shown on search engine results, bookmarks, etc.', 'maranatha' );

	/*---------------------------------------------
	 * Colors
	 *--------------------------------------------*/

	$section = 'colors';
	$wp_customize->get_section( $section )->priority = $section_priority += $section_increment; // section order
	$wp_customize->get_section( $section )->title = __( 'Colors', 'maranatha' ); // rename
	$control_priority = 0;

		// Main Color
		$setting = $option_id . '[main_color]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['main_color']['value'],
			'type'					=> $setting_type,
			'transport'				=> 'postMessage',
			'sanitize_callback'		=> 'maranatha_customize_sanitize_main_color',
		) );

			$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $setting, array(
				'label'		=> __( 'Main Color', 'maranatha' ),
				'section'	=> $section,
				'priority'	=> $control_priority += $control_increment,
			) ) );

		// Link Color
		$setting = $option_id . '[link_color]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['link_color']['value'],
			'type'					=> $setting_type,
			'transport'				=> 'postMessage',
			'sanitize_callback'		=> 'maranatha_customize_sanitize_link_color',
		) );

			$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $setting, array(
				'label'		=> __( 'Link Color', 'maranatha' ),
				'section'	=> $section,
				'priority'	=> $control_priority += $control_increment,
			) ) );

	/*---------------------------------------------
	 * Fonts
	 *--------------------------------------------*/

	$section = 'maranatha_fonts';
	$wp_customize->add_section( $section, array(
		'title'			=> _x( 'Fonts', 'customizer', 'maranatha' ),
		'description'	=> __( "A hand-picked selection of Google Fonts that work well with this theme's design.", 'maranatha' ),
		'priority'		=> $section_priority += $section_increment, // section order
	) );
	$control_priority = 0;

		// Logo Font
		$setting = $option_id . '[logo_font]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['logo_font']['value'],
			'type'					=> $setting_type,
			'transport'				=> 'postMessage',
			'sanitize_callback'		=> 'maranatha_customize_sanitize_logo_font',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> __( 'Logo Font', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'select',
				'choices'	=> ctfw_google_font_options_array( array(
								'target' 	=> 'logo_font',
								'show_type'	=> true
							) ),
				'priority'	=> $control_priority += $control_increment,
			) );

		// Menu Font
		$setting = $option_id . '[menu_font]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['menu_font']['value'],
			'type'					=> $setting_type,
			'transport'				=> 'postMessage',
			'sanitize_callback'		=> 'maranatha_customize_sanitize_menu_font',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> __( 'Menu Font', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'select',
				'choices'	=> ctfw_google_font_options_array( array(
								'target' 	=> 'menu_font',
								'show_type'	=> true
							) ),
				'priority'	=> $control_priority += $control_increment,
			) );

		// Heading Font
		$setting = $option_id . '[heading_font]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['heading_font']['value'],
			'type'					=> $setting_type,
			'transport'				=> 'postMessage',
			'sanitize_callback'		=> 'maranatha_customize_sanitize_heading_font',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> __( 'Heading Font', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'select',
				'choices'	=> ctfw_google_font_options_array( array(
								'target' 	=> 'heading_font',
								'show_type'	=> true
							) ),
				'priority'	=> $control_priority += $control_increment,
			) );

		// Body Font
		$setting = $option_id . '[body_font]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['body_font']['value'],
			'type'					=> $setting_type,
			'transport'				=> 'postMessage',
			'sanitize_callback'		=> 'maranatha_customize_sanitize_body_font',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> __( 'Body Font', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'select',
				'choices'	=> ctfw_google_font_options_array( array(
								'target' 	=> 'body_font',
								'show_type'	=> true
							) ),
				'priority'	=> $control_priority += $control_increment,
			) );

		// Character Sets
		$setting = $option_id . '[font_subsets]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['font_subsets']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'maranatha_customize_sanitize_font_subsets',
		) );

			$wp_customize->add_control( $setting, array(
				'label'			=> __( 'Character Sets (Optional)', 'maranatha' ),
				'section'		=> $section,
				'type'			=> 'text',
				'description'	=> __( 'Some fonts support multiple character sets (e.g. latin, cyrillic, greek, vietnamese). When none specified, latin is used. If necessary, enter multiple character sets separated by commas.', 'maranatha' ),
				'priority'		=> $control_priority += $control_increment,
			) );

	/*---------------------------------------------
	 * Logo
	 *--------------------------------------------*/

	$section = 'maranatha_logo';
	$wp_customize->add_section( $section, array(
		'title'			=> _x( 'Logo', 'customizer', 'maranatha' ),
		'description'	=>__( 'You can provide a logo image or text.', 'maranatha' ),
		'priority'		=> $section_priority += $section_increment, // section order
	) );
	$control_priority = 0;

		// Logo Type
		$setting = $option_id . '[logo_type]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['logo_type']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'maranatha_customize_sanitize_logo_type',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> _x( 'Logo Type', 'customizer', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'radio',
				'choices'	=> maranatha_customize_logo_type_choices(),
				'priority'	=> $control_priority += $control_increment,
			) );

		// Logo Image
		$setting = $option_id . '[logo_image]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['logo_image']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'esc_url_raw',
		) );

			$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $setting, array(
				'label'			=> _x( 'Logo Image', 'customizer', 'maranatha' ),
				'description'	=> sprintf(
										__( '<b>%1$s maximum</b> (transparent PNG recommended). Height is reduced to %2$s pixels on scroll and mobile.', 'maranatha' ),
										maranatha_logo_size( 'max_dimensions' ),
										maranatha_logo_size( 'max_height_small' ) // on sticky scrolling and mobile
									),
				'section'		=> $section,
				'priority'		=> $control_priority += $control_increment,
			) ) );

		// Logo Image - HiDPI / Retina
		$setting = $option_id . '[logo_hidpi]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['logo_hidpi']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'esc_url_raw',
		) );

			$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $setting, array(
				'label'		=> _x( 'HiDPI Logo (Optional)', 'customizer', 'maranatha' ),
				'description'	=> __( 'Also known as "Retina", should be exactly double the size of regular image.', 'maranatha' ),
				'section'	=> $section,
				'priority'	=> $control_priority += $control_increment,
			) ) );

		// Logo Text
		$setting = $option_id . '[logo_text]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['logo_text']['value'],
			'type'					=> $setting_type,
			'transport'				=> 'postMessage',
			'sanitize_callback'		=> 'maranatha_customize_sanitize_logo_text',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> __( 'Logo Text', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'text',
				'priority'	=> $control_priority += $control_increment
			) );

		// Logo Text Size
		$setting = $option_id . '[logo_text_size]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['logo_text_size']['value'],
			'type'					=> $setting_type,
			'transport'				=> 'postMessage',
			'sanitize_callback'		=> 'maranatha_customize_sanitize_logo_text_size',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> _x( 'Logo Text Size', 'customizer', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'radio',
				'choices'	=> maranatha_customize_logo_text_size_choices(),
				'priority'	=> $control_priority += $control_increment,
			) );

	/*---------------------------------------------
	 * Header Image
	 *--------------------------------------------*/

	$section = 'header_image';
	$wp_customize->get_section( $section )->priority = $section_priority += $section_increment; // section order
	$wp_customize->get_section( $section )->description = __( 'This image is shown in the header except on the homepage and unless a post or page has its own image. Go to Widgets > Homepage Sections to change the image used on the homepage.', 'maranatha' );
	$control_priority = 0;

		// Header Image appears automatically (via add_theme_support())
		// Other custom settings added below

		// Header Image Opacity
		$setting = $option_id . '[header_image_opacity]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['header_image_opacity']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'maranatha_customize_sanitize_header_image_opacity',
		) );

			$wp_customize->add_control( $setting, array(
				'label'			=> _x( 'Image Opacity (Percentage)', 'customizer', 'maranatha' ),
				'description'	=> __( 'Lower percentage makes color show through. Aim for easy to read text.', 'maranatha' ),
				'section'		=> $section,
				'type'			=> 'number',
				'input_attrs' => array(
					'min' => 1,
					'max' => 100,
					'style' => 'width:60px',
				),
				'priority'		=> $control_priority += $control_increment,
			) );

	/*---------------------------------------------
	 * Footer Content
	 *--------------------------------------------*/

	$section = 'maranatha_footer';
	$wp_customize->add_section( $section, array(
		'title'			=> _x( 'Footer', 'customizer', 'maranatha' ),
		'description' 	=> __( 'Go to Widgets to manage your Footer Widgets.', 'maranatha' ),
		'priority'		=> $section_priority += $section_increment, // section order
	) );
	$control_priority = 0;

		// Location
		$setting = $option_id . '[show_footer_location]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['show_footer_location']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'ctfw_customize_sanitize_checkbox',
		) );

			$wp_customize->add_control( $setting, array(
				'label'			=> _x( 'Show map in footer', 'customizer', 'maranatha' ),
				'description'	=> _x( 'A map for your primary location will be shown, except on pages where map is shown higher (homepage, location, etc.).', 'customizer', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'checkbox',
				'priority'	=> $control_priority += $control_increment,
			) );

		// Footer Icon URLs
		$setting = $option_id . '[footer_icon_urls]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['footer_icon_urls']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'maranatha_customize_sanitize_icon_urls',
		) );

			$wp_customize->add_control( $setting, array(
				'label'			=> __( 'Icon Link URLs', 'maranatha' ),
				'description'	=> sprintf( __( 'Enter one URL per line for %s. Use <code>[ctcom_rss_url]</code> for RSS.', 'maranatha' ), maranatha_social_icon_sites( 'or' ) ),
				'section'		=> $section,
				'type'			=> 'textarea',
				'priority'	=> $control_priority += $control_increment,
			) );

		// Notice
		$setting = $option_id . '[footer_notice]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['footer_notice']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'maranatha_customize_sanitize_footer_notice',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> _x( 'Notice', 'customizer', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'textarea',
				'priority'	=> $control_priority += $control_increment,
			) );

		// Bottom Left Sticky
		$setting = $option_id . '[bottom_left_sticky]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['bottom_left_sticky']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'maranatha_customize_sanitize_bottom_left_sticky',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> _x( 'Bottom Left Sticky', 'customizer', 'maranatha' ),
				'description'	=> __( 'Show content attached to the bottom of the screen when scrolling.', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'radio',
				'choices'	=> maranatha_customize_bottom_left_sticky_choices(),
				'priority'	=> $control_priority += $control_increment,
			) );

		// Bottom Left Sticky - Items Limit
		$setting = $option_id . '[bottom_left_sticky_items_limit]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['bottom_left_sticky_items_limit']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'maranatha_customize_sanitize_bottom_left_sticky_items_limit',
		) );

			$wp_customize->add_control( $setting, array(
				'label'			=> _x( 'Bottom Left Sticky Items', 'customizer', 'maranatha' ),
				'section'		=> $section,
				'type'			=> 'radio',
				'choices'		=> maranatha_customize_bottom_left_sticky_items_limit_choices(),
				'priority'		=> $control_priority += $control_increment,
			) );

		// Bottom Left Sticky - Custom Content
		$setting = $option_id . '[bottom_left_sticky_content]';
		$wp_customize->add_setting( $setting, array(
			'default'				=> $defaults['bottom_left_sticky_content']['value'],
			'type'					=> $setting_type,
			'sanitize_callback'		=> 'maranatha_customize_sanitize_bottom_left_sticky_content',
		) );

			$wp_customize->add_control( $setting, array(
				'label'		=> __( 'Custom Content', 'maranatha' ),
				'section'	=> $section,
				'type'		=> 'text',
				'priority'	=> $control_priority += $control_increment,
			) );
	/*---------------------------------------------
	 * Homepage - Static Front Page (core)
	 *--------------------------------------------*/

	// Static Front Page (core)
	$section = 'static_front_page';
	if ( $wp_customize->get_section( $section ) ) { // section will not exist if no Pages have been made yet

		$wp_customize->get_section( $section )->title = _x( 'Homepage', 'customizer', 'maranatha' ); // rename from Static Front Page
		$wp_customize->get_section( $section )->description = __( 'Create a page that uses the Homepage template and set it as your Front Page. Set Posts Page to a page using the Blog template. <br><br>Go to Widgets to manage your Homepage Sections.', 'maranatha' );
		$wp_customize->get_section( $section )->priority = $section_priority += $section_increment; // section order
		$control_priority = 0;

			// Map / Location
			$setting = $option_id . '[show_home_location]';
			$wp_customize->add_setting( $setting, array(
				'default'				=> $defaults['show_home_location']['value'],
				'type'					=> $setting_type,
				'sanitize_callback'		=> 'ctfw_customize_sanitize_checkbox',
			) );

				$wp_customize->add_control( $setting, array(
					'label'			=> __( 'Show map on homepage', 'maranatha' ),
					'description'	=> __( 'A map for your primary location will be shown after the first section on the homepage.', 'maranatha' ),
					'section'		=> $section,
					'type'			=> 'checkbox',
					'priority'		=> $control_priority += $control_increment,
				) );

	}

	/*---------------------------------------------
	 * Widgets
	 *--------------------------------------------*/

	// Widgets (core) - move before "Additional CSS"
	$panel = (object) $wp_customize->get_panel( 'widgets' ); // prevent "Creating default object from empty value" warning in PHP 5.4
	$panel->priority = 199; // panel/section order

}

add_action( 'customize_register', 'maranatha_customize_register' );

/*********************************************
 * SANITIZATION
 *********************************************/

// Sanitize all fields to prevent incorrect or dangerous input.

/*---------------------------------------------
 * Colors
 *--------------------------------------------*/

/**
 * Sanitize Main Color
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_main_color( $input, $object ) {

	// Validate hex code; if empty or invalid, use default
	$output = ctfw_customize_sanitize_color( 'main_color', $input );

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_main_color', $output, $input, $object );

}

/**
 * Sanitize Link Color
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_link_color( $input, $object ) {

	// Sanitize hex code ; if empty or invalid, use default
	$output = ctfw_customize_sanitize_color( 'link_color', $input );

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_link_color', $output, $input, $object );

}

/*---------------------------------------------
 * Fonts
 *--------------------------------------------*/

/**
 * Sanitize Logo Font
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_logo_font( $input, $object ) {

	// Check input against choices; use default if empty value not permitted
	$output = ctfw_customize_sanitize_google_font( 'logo_font', $input );

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_logo_font', $output, $input, $object );

}

/**
 * Sanitize Menu Font
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_menu_font( $input, $object ) {

	// Check input against choices; use default if empty value not permitted
	$output = ctfw_customize_sanitize_google_font( 'menu_font', $input );

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_menu_font', $output, $input, $object );

}

/**
 * Sanitize Heading Font
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_heading_font( $input, $object ) {

	// Check input against choices; use default if empty value not permitted
	$output = ctfw_customize_sanitize_google_font( 'heading_font', $input );

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_heading_font', $output, $input, $object );

}

/**
 * Sanitize Body Font
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_body_font( $input, $object ) {

	// Check input against choices; use default if empty value not permitted
	$output = ctfw_customize_sanitize_google_font( 'body_font', $input );

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_body_font', $output, $input, $object );

}

/**
 * Sanitize Google Font Character Sets
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_font_subsets( $input, $object ) {

	// Remove whitespace, HTML, etc.
	$output = sanitize_text_field( $input );

	// Return sanitized filterable
	return apply_filters( 'maranatha_customize_sanitize_font_subsets', $output, $input, $object );

}

/*---------------------------------------------
 * Logo
 *--------------------------------------------*/

/**
 * Sanitize Logo Type
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_logo_type( $input, $object ) {

	// Check input against choices; use default if empty value not permitted
	$output = ctfw_customize_sanitize_single_choice( 'logo_type', $input, maranatha_customize_logo_type_choices() ); // ctfw_customize_sanitize_single_choice is for radio or single select

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_logo_type', $output, $input, $object );

}

/**
 * Sanitize Logo Text
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_logo_text( $input, $object ) {

	// Remove whitespace, HTML, etc.
	$output = sanitize_text_field( $input );

	// Return sanitized filterable
	return apply_filters( 'maranatha_customize_sanitize_logo_text', $output, $input, $object );

}

/**
 * Sanitize Logo Text Size
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_logo_text_size( $input, $object ) {

	// Check input against choices; use default if empty value not permitted
	$output = ctfw_customize_sanitize_single_choice( 'logo_text_size', $input, maranatha_customize_logo_text_size_choices() ); // ctfw_customize_sanitize_single_choice is for radio or single select

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_logo_text_size', $output, $input, $object );

}

/*---------------------------------------------
 * Header Image
 *--------------------------------------------*/

/**
 * Header Image Opacity
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return int Non-negative number between 1 and 100
 */
function maranatha_customize_sanitize_header_image_opacity( $input, $object ) {

	// Force non-negative numeric value
	$output = absint( $input );

	// If 0 set it to 1
	if ( 0 == $output ) {
		$output = 1;
	}

	// If more than 100, set to 100
	if ( $output > 100 ) {
		$output = 100;
	}

	// Return sanitized filterable
	return apply_filters( 'maranatha_customize_sanitize_header_image_opacity', $output, $input, $object );

}

/*---------------------------------------------
 * Footer Content
 *--------------------------------------------*/

/**
 * Show Location -- done directly with ctfw_customize_sanitize_checkbox()
 */

/**
 * Sanitize Icon URLs
 *
 * Used on header and footer URL lists for icons.
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized list of URLs
 */
function maranatha_customize_sanitize_icon_urls( $input, $object ) {

	// Remove empty lines and sanitize URLs
	$output = ctfw_sanitize_url_list( $input, array(
		'[ctcom_rss_url]' // allow this string instead of URL
	) );

	// Return sanitized filterable
	return apply_filters( 'maranatha_customize_sanitize_icon_urls', $output, $input, $object );

}

/**
 * Sanitize Footer Notice
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Content with "safe" HTML
 */
function maranatha_customize_sanitize_footer_notice( $input, $object ) {

	// Allow HTML (same as posts), no scripts
	$output = stripslashes( wp_filter_post_kses( addslashes( $input ) ) );

	// Balance tags (may be using HTML for link)
	$output = force_balance_tags( $output );

	// Return sanitized filterable
	return apply_filters( 'maranatha_customize_sanitize_footer_notice', $output, $input, $object );

}

/**
 * Sanitize Bottom Left Sticky
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_bottom_left_sticky( $input, $object ) {

	// Check input against choices; use default if empty value not permitted
	$output = ctfw_customize_sanitize_single_choice( 'bottom_left_sticky', $input, maranatha_customize_bottom_left_sticky_choices() ); // ctfw_customize_sanitize_single_choice is for radio or single select

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_bottom_left_sticky', $output, $input, $object );

}

/**
 * Sanitize Bottom Left Sticky Items Limit
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Sanitized value
 */
function maranatha_customize_sanitize_bottom_left_sticky_items_limit( $input, $object ) {

	// Check input against choices; use default if empty value not permitted
	$output = ctfw_customize_sanitize_single_choice( 'bottom_left_sticky_items_limit', $input, maranatha_customize_bottom_left_sticky_items_limit_choices() ); // ctfw_customize_sanitize_single_choice is for radio or single select

	// Return sanitized, filterable
	return apply_filters( 'maranatha_customize_sanitize_bottom_left_sticky_items_limit', $output, $input, $object );

}

/**
 * Sanitize Bottom Left Sticky Custom Content
 *
 * @since 1.0
 * @param string $input Unsanitized value submitted by user
 * @param object $object
 * @return string Content with "safe" HTML
 */
function maranatha_customize_sanitize_bottom_left_sticky_content( $input, $object ) {

	// Allow HTML (same as posts), no scripts (better to child theme it)
	$output = stripslashes( wp_filter_post_kses( addslashes( $input ) ) );

	// Balance tags (may be using HTML for link)
	$output = force_balance_tags( $output );

	// Return sanitized filterable
	return apply_filters( 'maranatha_customize_sanitize_bottom_left_sticky_content', $output, $input, $object );

}

/*********************************************
 * SCRIPTS & STYLES
 *********************************************/

/**
 * Enqueue JavaScript for customizer controls
 *
 * @since 1.0
 */
function maranatha_customize_enqueue_scripts() {

	// doTimeout used by admin-customize.js
	wp_enqueue_script( 'jquery-ba-dotimeout', get_theme_file_uri( CTFW_THEME_JS_DIR . '/lib/jquery.ba-dotimeout.min.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update

	// Script that handles dynamic display of controls
	wp_enqueue_script( 'maranatha-admin-customize', get_theme_file_uri( CTFW_THEME_JS_DIR . '/admin-customize.js' ), array( 'jquery' ), CTFW_THEME_VERSION ); // bust cache on theme update

	// Make data available to script
	wp_localize_script( 'maranatha-admin-customize', 'maranatha_customize', array(
		'option_id' => ctfw_customize_option_id()
	));

}

add_action( 'customize_controls_print_scripts', 'maranatha_customize_enqueue_scripts' );

/**
 * Enqueue styles for customizer controls
 *
 * @since 1.4
 */
function maranatha_customize_enqueue_styles() {

	// Admin widgets
	// Same stylesheet used for Appearance > Widgets
	wp_enqueue_style( 'maranatha-admin-widgets', get_theme_file_uri( CTFW_THEME_CSS_DIR . '/admin/admin-widgets.css' ), false, CTFW_THEME_VERSION ); // bust cache on update

}

add_action( 'customize_controls_print_styles', 'maranatha_customize_enqueue_styles' );

/**
 * Enqueue JavaScript for customizer live preview
 *
 * @since 1.0
 */
function maranatha_customize_preview_enqueue_scripts() {

	// Google Web Font Loader
	wp_enqueue_script( 'google-webfont-loader', '//ajax.googleapis.com/ajax/libs/webfont/1/webfont.js', false, null ); // null - don't mess with Google Fonts URL by adding version

	// Enqueue preview script
	wp_enqueue_script( 'maranatha-admin-customize-preview', get_theme_file_uri( CTFW_THEME_JS_DIR . '/admin-customize-preview.js' ), false, CTFW_THEME_VERSION ); // bust cache on theme update

	// Make data available to script
	wp_localize_script( 'maranatha-admin-customize-preview', 'maranatha_customize_preview', array(
		'option_id' 						=> ctfw_customize_option_id(),
		'fonts' 							=> ctfw_google_fonts(),
		'logo_font_selectors'				=> maranatha_style_selectors( 'logo_font' ),
		'menu_font_selectors'				=> maranatha_style_selectors( 'menu_font' ),
		'heading_font_selectors'			=> maranatha_style_selectors( 'heading_font' ),
		'body_font_selectors'				=> maranatha_style_selectors( 'body_font' ),
		'main_color_selectors'				=> maranatha_style_selectors( 'main_color' ),
		'main_color_border_selectors'		=> maranatha_style_selectors( 'main_color_border' ),
		'main_color_text_selectors'			=> maranatha_style_selectors( 'main_color_text' ),
		'link_color_selectors'				=> maranatha_style_selectors( 'link_color' ),
		'link_color_border_selectors'		=> maranatha_style_selectors( 'link_color_border' ),
		'link_color_border_left_selectors'	=> maranatha_style_selectors( 'link_color_border_left' ),
		'link_color_bg_selectors'			=> maranatha_style_selectors( 'link_color_bg' ),
	));

}

add_action( 'customize_preview_init', 'maranatha_customize_preview_enqueue_scripts' );
