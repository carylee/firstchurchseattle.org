<?php
/**
 * Template Name: Gallery
 *
 * This template exists to signify that the page is a gallery for purposes of content type, widget visiblity, etc.
 *
 * partials/content-full.php outputs the page content.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Load main template to show the page
locate_template( 'index.php', true );
