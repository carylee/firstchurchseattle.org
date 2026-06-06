<?php
/**
 * Template Name: Wide Content (1170px)
 *
 * Use content width of 1170 pixels.
 *
 * partials/content-full.php outputs the page content.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Load main template to cause content.php to show content normally
locate_template( 'index.php', true );