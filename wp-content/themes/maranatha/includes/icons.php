<?php
/**
 * Icon Functions
 *
 * @package    Maranatha
 * @subpackage Functions
 * @copyright  Copyright (c) 2015 - 2019, ChurchThemes.com
 * @link       https://churchthemes.com/themes/maranatha
 * @license    GPLv2 or later
 * @since      1.0
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

/***********************************
 * ICON FONT
 ***********************************

/**
 * Get icon class
 *
 * Return icon class for specific element, for easy filtering to replace icons in specific areas.
 *
 * For social icons to filter, see maranatha_social_icon_map() below.
 *
 * @since 1.0
 * @param string $element Element icon used with
 * @return string Icon class
 */
function maranatha_get_icon_class( $element ) {

	// Elements and their classes
	$classes = array(
		'search-button'			=> 'el el-search', 				// header and widget
		'search-cancel'			=> 'el el-remove-sign',
		'mobile-menu-close'		=> 'el el-remove-sign',
		'nav-left'				=> 'el el-chevron-left', 		// prev/next navigation
		'nav-right'				=> 'el el-chevron-right', 		// prev/next navigation
		'archive-dropdown'		=> 'el el-chevron-down', 		// header archive dropdowns
		'comment-reply'			=> 'el el-comment', 			// "Reply" button on comment
		'comment-edit'			=> 'el el-edit', 				// "Edit" button on comment
		'edit-post'				=> 'el el-edit',				// edit button for any post type
		'gallery'				=> 'el el-camera',
		'entry-tag'				=> 'el el-tags',
		'download'				=> 'el el-download-alt', 		// generic download
		'video-watch'			=> 'el el-video',				// sermon
		'video-download'		=> 'el el-video', 				// sermon
		'audio-listen'			=> 'el el-headphones', 			// sermon
		'audio-download'		=> 'el el-headphones', 			// sermon
		'pdf-download'			=> 'el el-file', 				// sermon
		'sermon-read'			=> 'el el-align-justify',
		'sermon-topic'			=> 'el el-folder',				// widget and archive/index
		'sermon-book'			=> 'el el-book',				// widget and archive/index
		'sermon-series'			=> 'el el-forward-alt',			// widget and archive/index
		'sermon-speaker'		=> 'el el-torso',				// widget and archive/index
		'sermon-date'			=> 'el el-calendar',			// widget and archive/index
		'event-directions'		=> 'el el-road',
		'calendar-remove'		=> 'el el-remove',				// remove category filter
		'calendar-prev'			=> 'el el-chevron-left',
		'calendar-next'			=> 'el el-chevron-right',
		'calendar-month'		=> 'el el-calendar',
		'calendar-category'		=> 'el el-folder',
		'location-directions'	=> 'el el-road',
		'map-venue'				=> 'el el-flag',
		'map-phone'				=> 'el el-phone-alt',
		'map-address'			=> 'el el-map-marker',
		'map-times'				=> 'el el-time',
		'map-email'				=> 'el el-envelope',
	);

	// Make array filterable
	$classes = apply_filters( 'maranatha_icon_classes', $classes, $element );

	// Get class for element
	$class = '';
	if (! empty( $classes[$element] )) {
		$class = $classes[$element];
	}

	// Return filterable
	return apply_filters( 'maranatha_get_icon_class', $class, $element );

}

/**
 * Output icon class
 *
 * Output contents of maranatha_get_icon_class()
 *
 * @since 1.0
 * @param string $element Element icon used with
 * @param bool $return Whether or not to return (false echos)
 * @return string If echoing class
 */
function maranatha_icon_class( $element, $return = false ) {

	$class = apply_filters( 'maranatha_icon_class', maranatha_get_icon_class( $element ) );

	if ($return) {
		return $class;
	} else {
		echo $class;
	}

}

/***********************************
 * SOCIAL ICONS (Header/Footer)
 ***********************************

/**
 * Icons available
 *
 * This is used in displaying icons with maranatha_social_icons() and
 * to tell which social networks are supported with maranatha_social_icon_sites().
 *
 * @since 1.0
 * @return array Icon map
 */
function maranatha_social_icon_map() {

	 // Social media sites with icons
	$icon_map = array(

		// CSS Class 						// Match in URL 	// Site Name 		// Hide in list
		'el el-facebook'		=> array(	'facebook',			'Facebook' ),
		'el el-twitter'			=> array(	'twitter',			'Twitter' ),
		'el el-googleplus'		=> array(	'plus.google',		'Google+' ),
		'el el-pinterest'		=> array( 	'pinterest',		'Pinterest' ),
		'el el-youtube'			=> array( 	'youtube',			'YouTube' ),
		'el el-vimeo'			=> array( 	'vimeo', 			'Vimeo' ),
		'el el-instagram'		=> array( 	'instagram',		'Instagram' ),
		'el el-soundcloud'		=> array( 	'soundcloud', 		'SoundCloud' ),
		'el el-flickr'			=> array( 	'flickr',			'Flickr' ),
		'el el-spotify'			=> array( 	'spotify',			'Spotify' ),
		'el el-picasa'			=> array( 	'picasa',			'Picasa',           true ),
		'el el-foursquare'		=> array( 	'foursquare',		'Foursquare',       true ),
		'el el-skype'			=> array( 	'skype', 			'Skype',            true ),
		'el el-linkedin'		=> array( 	'linkedin', 		'LinkedIn',         true ),
		'el el-tumblr'			=> array( 	'tumblr',			'Tumblr',			true ),
		'el el-github'			=> array( 	'github',			'GitHub',			true ),
		'el el-dribbble'		=> array( 	'dribbble',			'Dribbble',			true ),
		'el el-wordpress'		=> array( 	'wordpress',		'WordPress',		true ),
		'el el-envelope-alt'	=> array( 	array( 'mailto', 'newsletter' ), 'Email' ),
		'el el-podcast'			=> array( 	array( 'itunes', 'podcast', 'sermonaudio.com', 'play.google.com', 'playmusic.app.goo.gl', 'castbox', 'libsyn' ), 'Podcast' ),
		'el el-rss'				=> array( 	array( 'rss', 'feed', 'atom' ), 'RSS' ),
		'el el-website-alt'		=> array( 	'http', 			'Website' ), // anything not matching the above will show a generic website icon

	);

	// Return filtered
	return apply_filters( 'maranatha_social_icon_map', $icon_map );

}

/**
 * List of sites with icons
 *
 * Shown to user in Theme Customizer
 *
 * @since 1.0
 * @param bool $or True to use "or"; otherwise "and"
 * @return string List of sites with icons
 */
function maranatha_social_icon_sites( $or = false ) {

	$icon_map = maranatha_social_icon_map();

	$sites_with_icons = '';

	$i = 0;

	// Remove hidden entries
	foreach ($icon_map as $class => $site_data) { // make list of sites with icons
		if (! empty( $site_data[2] )) {
			unset( $icon_map[$class] );
		}
	}

	// Count sites
	$sites_with_icons_count = count( $icon_map );

	// Build list
	foreach ($icon_map as $site_data) { // make list of sites with icons

		$match = $site_data[0];
		$name = $site_data[1];

		$i++;

		if ($i > 1) { // not first one
			if ($i < $sites_with_icons_count) { // not last one
				$sites_with_icons .= _x( ', ', 'social icons list', 'maranatha' );
			} else { // last one
				if (! empty( $or )) {
					$sites_with_icons .= _x( ' or ', 'social icons list', 'maranatha' );
				} else {
					$sites_with_icons .= _x( ' and ', 'social icons list', 'maranatha' );
				}
			}
		}

		$sites_with_icons .= $name;

	}

	return apply_filters( 'maranatha_social_icon_sites', $sites_with_icons );

}

/**
 * Show icons
 *
 * @since 1.0
 * @param array $urls URLs set in Customizer
 * @param bool $return Return or echo
 * @return string Icons HTML if not echoing
 */
function maranatha_social_icons( $urls, $return = false ) {

	$icon_list = '';

	// Social media URLs defined in Customizer
	if (! empty( $urls )) {

		// Available Icons
		$icon_map = maranatha_social_icon_map();

		// Loop URLs (in order entered by user) to build icon list
		$icon_items = '';
		$url_array = explode( "\n", $urls );
		foreach ($url_array as $url) {

			$url = trim( $url );

			// URL is valid
			if (! empty( $url ) && ( '[ctcom_rss_url]' == $url || preg_match( '/^(http(s*)):\/\/(.+)\.(.+)|skype:(.+)|mailto:(.+)@(.+)\.(.+)/i', $url ) )) { // basic URL check

				// Find matching icon
				foreach ($icon_map as $icon_class => $site_data) {

					// Data
					$match = $site_data[0];
					$name = $site_data[1];

					// If "Any Website", use domain of website as name
					// Useful because it may not be a website at all being linked to (social profile, feed, etc.)
					// This is just a little more descriptive
					$domain_as_name = false;
					if ('http' == $match || 'Website' == $name) {
						$domain_as_name = true;
					}

					// Prepare to match
					$url_checks = (array) $match;
					$url_matched = false;

					// Loop each string to match
					foreach ($url_checks as $url_match) {

						// Check URL for matching string
						if (preg_match( '/' . preg_quote( $url_match ) . '/i', $url ) && ! $url_matched) {

							// Success
							$url_matched = true;

							// Use domain as name (see above)
							if ($domain_as_name) {
								$parsed_url = parse_url( $url );
								$name = ! empty( $parsed_url['host'] ) ? str_replace( 'www.', '', $parsed_url['host'] ) : $name;
							}

							// Run shortcodes for [ctcom_rss_url]
							$url = do_shortcode( $url );

							// Acceptable protocols.
							// Allow skype.
							$protocols = array_merge( wp_allowed_protocols(), array(
								'skype',
							) );

							// Append icon
							$icon_items .= '	<li><a href="' . esc_url( $url, $protocols ) . '" class="' . esc_attr( $icon_class ) . '" title="' . esc_attr( $name ) . '" target="' . apply_filters( 'maranatha_social_icons_link_target', '_blank' ) . '" rel="noopener noreferrer"></a></li>' . "\n";

						}

					}

					// Done
					if ($url_matched) {
						break;
					}

				}

			}

		}

		// Wrap with <ul> tags and apply shortcodes
		if (! empty( $icon_items )) {
			$icon_list = '<ul class="maranatha-list-icons">' . "\n";
			$icon_list .= $icon_items;
			$icon_list .= '</ul>';
		}

	}

	// Echo or return filtered
	$icon_list = apply_filters( 'maranatha_social_icons', $icon_list, $urls );
	if ($return) {
		return $icon_list;
	} else {
		echo $icon_list;
	}

}
