<?php
/**
 * Theme Header — child override (pinned verbatim copy).
 *
 * Outputs <head> and header content (logo, navigation, search icon, banner,
 * breadcrumb, etc.).
 *
 * Owned by the child as part of the theme-independence work (extracting the
 * base template skeleton so the maranatha parent can eventually be dropped —
 * see ops/docs/theme-independence.md). Started as a verbatim copy of the
 * parent's header.php; the parent's CTFW_THEME_PARTIAL_DIR constant is now
 * literalized to 'partials' so this file no longer fatals once the parent (and
 * its constant) is gone. The header-top / header-bottom sub-partials it pulls
 * still resolve to the parent (the child has no copy yet) — tracked as a
 * remaining dependency.
 *
 * Parent source: maranatha/header.php (pinned in this repo) — re-diff if the
 * parent theme is ever updated.
 *
 * @package Maranatha_Child
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

	<?php get_template_part( 'partials/header-top' ); // header-top.php ?>

	<?php get_template_part( 'partials/header-banner' ); ?>

	<?php get_template_part( 'partials/header-bottom' ); // breadcrumbs (left), archive dropdowns (right) ?>

</header>
