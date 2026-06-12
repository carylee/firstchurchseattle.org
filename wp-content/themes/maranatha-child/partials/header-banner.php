<?php
/**
 * Header Banner — child theme override.
 *
 * Replaces the parent's heavy 8%-padded, table-celled, faded-photo banner
 * with a flat compact maroon strip showing just the page title. Cross-page,
 * works at every viewport.
 *
 * The parent's get_template_part() lookup finds this file first (child theme
 * takes precedence), so this just exists at the same path as the parent's
 * partials/header-banner.php.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Homepage uses a different template entirely.
if ( is_page_template( 'page-templates/homepage.php' ) ) {
	return;
}

?>
<div id="maranatha-banner" class="bg-brand">
	<!--
		The parent theme's #maranatha-header-top is `position: fixed` and ~78–135px
		tall depending on viewport. Our banner needs enough top padding to push
		its content below that fixed bar so the H1 doesn't overlap with the nav.
		`pt-32` (8rem ≈ 128px) clears the header on desktop; `pt-24` (6rem ≈ 96px)
		is enough on mobile where the header collapses.
	-->
	<div class="max-w-5xl mx-auto px-6 pt-24 sm:pt-32 pb-6 sm:pb-8 flex items-center justify-center">
		<h1 class="m-0 text-white font-display font-medium text-3xl sm:text-4xl tracking-wide text-center">
			<?php maranatha_title_paged(); /* echoes the page title */ ?>
		</h1>
	</div>
</div>
