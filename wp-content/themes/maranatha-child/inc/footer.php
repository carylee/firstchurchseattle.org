<?php
/**
 * Footer tweaks — parent-theme overrides via filter (no footer.php override,
 * no Customizer/DB edit).
 *
 * Maranatha's footer prints ctfw_customization('footer_notice'); the theme's
 * default notice appends a "Powered by ChurchThemes.com" credit. Strip just that
 * credit (keep the copyright line) through the framework's own filter.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'ctfw_customization',
	function ( $value, $option ) {
		if ( 'footer_notice' !== $option || ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		// Remove the "— Powered by ChurchThemes.com" credit (linked or plain),
		// along with a leading separator. Scoped to the ChurchThemes credit so a
		// hand-written notice is never touched.
		$stripped = preg_replace(
			'~\s*[-–—|]?\s*Powered by\s*(?:<a\b[^>]*churchthemes\.com[^>]*>.*?</a>|ChurchThemes(?:\.com)?)~is',
			'',
			$value
		);
		return null === $stripped ? $value : trim( $stripped );
	},
	10,
	2
);
