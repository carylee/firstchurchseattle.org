<?php
/**
 * Template Name: Wide Content (980px)
 *
 * Use content width of 980 pixels.
 *
 * partials/content-full.php outputs the page content.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Load main template to cause content.php to show content normally
locate_template( 'index.php', true );