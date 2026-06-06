<?php
/* Template Name: Homepage */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Header
get_header();

// Start loop
while ( have_posts() ) : the_post();

// Show sections
get_sidebar( 'home-sections' );

// End loop
endwhile;

// Footer
get_footer();