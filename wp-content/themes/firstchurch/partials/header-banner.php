<?php
/**
 * Page-title banner — a flat maroon strip under the site header.
 *
 * Loaded by header.php on every page except the front page (which opens with
 * the hero). The heading comes from fcs_page_title() (inc/theme-compat.php),
 * which resolves the right title for singular pages, archives, search and 404.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="fcs-banner">
	<div class="fcs-banner__inner">
		<?php $fcs_kicker = fcs_banner_kicker(); ?>
		<?php if ( $fcs_kicker ) : ?>
			<p class="fcs-kicker"><?php echo esc_html( $fcs_kicker ); ?></p>
		<?php endif; ?>
		<h1><?php echo esc_html( fcs_page_title() ); ?></h1>
	</div>
</div>
