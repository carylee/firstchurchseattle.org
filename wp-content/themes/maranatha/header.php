<?php
/**
 * Theme Header
 *
 * Outputs <head> and header content (logo, navigation, search icon, banner, breadcrumb, etc.).
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

?><!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="http://gmpg.org/xfn/11">
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />
<?php wp_head(); // prints out <title>, JavaScript, CSS, etc. as needed by WordPress, theme, plugins, etc. ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header id="maranatha-header">

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/header-top' ); // header-top.php ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/header-banner' ); ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/header-bottom' ); // breadcrumbs (left), archive dropdowns (right) ?>

</header>
