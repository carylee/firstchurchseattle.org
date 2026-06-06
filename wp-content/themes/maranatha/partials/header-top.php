<?php
/**
 * Header Top
 *
 * Outputs logo and menu / search.
 *
 * This is loaded by header.php.
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

?>

<div id="maranatha-header-top" class="<?php if (ctfw_customization( 'top_search' )) : ?>maranatha-has-header-search<?php else : ?>maranatha-no-header-search<?php endif; ?>">

	<div>

		<div id="maranatha-header-top-bg" class="maranatha-color-main-bg"></div>

		<div id="maranatha-header-top-container" class="maranatha-centered-large">

			<div id="maranatha-header-top-inner">

				<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/header-logo' ); ?>

				<nav id="maranatha-header-menu">

					<div id="maranatha-header-menu-inner">

						<?php
						wp_nav_menu( array(
							'theme_location'	=> 'header',
							'menu_id'			=> 'maranatha-header-menu-content',
							'menu_class'		=> 'sf-menu',
							'depth'				=> 3, // no more than 2 sub menus or risks running of screen either side
							'container'			=> false, // don't wrap in div
							'fallback_cb'		=> false, // don't show pages if no menu found - show nothing
							//'walker'			=> new CTFW_Walker_Nav_Menu_Description

						) );
						?>

					</div>

				</nav>

				<div id="maranatha-header-search" role="search">

					<div id="maranatha-header-search-opened">

						<?php get_search_form(); ?>

						<a href="#" id="maranatha-header-search-close" class="<?php maranatha_icon_class( 'search-cancel' ); ?>" title="<?php echo esc_attr(_x('Close Search', 'top bar search icon', 'maranatha')); ?>"></a>

					</div>

					<div id="maranatha-header-search-closed">
						<a href="#" id="maranatha-header-search-open" class="<?php maranatha_icon_class( 'search-button' ); ?>" title="<?php echo esc_attr(_x('Open Search', 'top bar search icon', 'maranatha')); ?>"></a>
					</div>

				</div>

			</div>

		</div>

	</div>

	<div id="maranatha-header-mobile-menu"></div>

</div>
