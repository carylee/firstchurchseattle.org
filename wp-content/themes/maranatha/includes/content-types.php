<?php
/**
 * Content Type Functions
 *
 * ctfw_current_content_type() in framework returns sermon, event, people, location, blog, page or search
 * depending on current post type, taxonomy, page template and other conditions.
 * This is useful for determining which sidebar to show or displaying a banner, for example.
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

/*********************************
 * CONTENT TYPES
 *********************************

/**
 * Update content types
 *
 * Page templates are theme-specific, so they should be filtered in here.
 * List page templates in order of priority for ctfw_redirect_archives_to_pages().
 *
 * Any of the other data can be changed or added. New content types can also be added.
 *
 * @since 1.0
 * @param array $content_types Default content types configuration from framework
 * @return array Modified content types
 */
function maranatha_update_content_types( $content_types ) {

	// Add page templates to existing content types
	$content_types['sermon']['page_templates']		= array( 'sermons.php', 'sermon-topics.php', 'sermon-books.php', 'sermon-series.php', 'sermon-speakers.php', 'sermon-dates.php' );
	$content_types['event']['page_templates']		= array( 'events-calendar.php', 'events-upcoming.php', 'events-past.php' );
	$content_types['people']['page_templates']		= array( 'people.php' );
	$content_types['location']['page_templates'] 	= array( 'locations.php' );
	$content_types['gallery']['page_templates'] 	= array( 'gallery.php', 'galleries.php' );
	$content_types['contact']['page_templates'] 	= array( 'contact.php' );
	$content_types['blog']['page_templates'] 		= array( 'blog.php' );

	// Specify event month archive URL format since this is theme-specific
	// Framework will link to month archives using this format via ctfw_events_month_archive_url()
	// Example: This theme has a Monthly Calendar page template that ctfw_content_type_archives() links to.
	// Replacement tags: {year}, {month}, {month_padded}
	$content_types['event']['month_archive_url_format'] = ctfw_get_page_url_by_template( 'events-calendar.php' )
														  . '?month={year}-{month_padded}';

	return $content_types;

}

add_filter( 'ctfw_content_types', 'maranatha_update_content_types' );
