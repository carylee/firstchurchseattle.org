<?php
/**
 * Theme header: <head>, the sticky site header (logo, primary nav, search,
 * mobile panel), and the page-title banner on non-front pages.
 *
 * Dropdown menus are CSS-driven (hover / focus-within); the nav island
 * (assets/js/islands/nav.js) layers on touch support, aria state, and the
 * mobile/search toggles. Without JS the desktop menu still works; the mobile
 * toggles are buttons that simply do nothing, which is the accepted
 * progressive-enhancement floor (same as the old jQuery menu).
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="fcs-header" data-fcs-nav>

	<div class="fcs-header__inner">

		<a class="fcs-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php // The header bar is brand maroon in both light and dark mode (see --fcs-maroon in base.css), so the white wordmark is correct in both; the black variant is for light backgrounds elsewhere. ?>
			<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/logo-white.svg' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="171" height="44">
		</a>

		<nav class="fcs-nav" aria-label="<?php esc_attr_e( 'Primary', 'firstchurch' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'header',
					'menu_class'     => 'fcs-nav__list',
					'container'      => false,
					'depth'          => 3,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>

		<div class="fcs-header__actions">

			<button type="button" class="fcs-header__btn fcs-search-toggle" aria-expanded="false" aria-controls="fcs-search" aria-label="<?php esc_attr_e( 'Search', 'firstchurch' ); ?>">
				<svg class="fcs-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.8-3.8"/></svg>
				<svg class="fcs-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
			</button>

			<button type="button" class="fcs-header__btn fcs-nav-toggle" aria-expanded="false" aria-controls="fcs-mobile" aria-label="<?php esc_attr_e( 'Menu', 'firstchurch' ); ?>">
				<svg class="fcs-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
				<svg class="fcs-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
			</button>

		</div>

	</div>

	<div id="fcs-search" class="fcs-search" hidden>
		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="fcs-search-input"><?php esc_html_e( 'Search for:', 'firstchurch' ); ?></label>
			<input type="search" id="fcs-search-input" name="s" placeholder="<?php esc_attr_e( 'Search the site…', 'firstchurch' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
			<button type="submit" class="btn-primary"><?php esc_html_e( 'Search', 'firstchurch' ); ?></button>
		</form>
	</div>

	<div id="fcs-mobile" class="fcs-mobile" hidden>
		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="fcs-mobile-search-input"><?php esc_html_e( 'Search for:', 'firstchurch' ); ?></label>
			<input type="search" id="fcs-mobile-search-input" name="s" placeholder="<?php esc_attr_e( 'Search the site…', 'firstchurch' ); ?>">
			<button type="submit" class="btn-primary"><?php esc_html_e( 'Search', 'firstchurch' ); ?></button>
		</form>
		<nav aria-label="<?php esc_attr_e( 'Primary (mobile)', 'firstchurch' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'header',
					'container'      => false,
					'depth'          => 3,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
	</div>

</header>

<?php
// Page-title banner on everything except the front page (which opens with
// the hero) and the detail templates that set their own in-page <h1>
// (event singles, staff profiles).
if ( ! is_front_page() && ! is_singular( array( 'fce_event', 'ctc_person' ) ) ) {
	get_template_part( 'partials/header-banner' );
}
