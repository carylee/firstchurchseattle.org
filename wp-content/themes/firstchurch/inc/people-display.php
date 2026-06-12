<?php
/**
 * People display, child-theme side. The firstchurch-people plugin owns the
 * ctc_person *data*; this owns its *presentation* — the /staff/ archive and the
 * single profile — the same split as firstchurch-events / single-fce_event.php.
 *
 * GATED on the plugin's fcs_people_active(): while Church Theme Content + the
 * legacy theme still rendered people, these swaps stayed off and the live
 * /staff/ pages are untouched. They take over automatically at the CTC cutover.
 * Self-contained on purpose — they bypass loop.php and the parent's
 * content-person-* partials, so nothing here depends on the parent surviving.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True only when the plugin is present AND owns the person type (post-cutover). */
function fcs_child_people_active(): bool {
	return function_exists( 'fcs_people_active' ) && fcs_people_active();
}

add_filter(
	'single_template',
	static function ( string $template ): string {
		if ( fcs_child_people_active() && is_singular( 'ctc_person' ) ) {
			return get_stylesheet_directory() . '/templates/person-single.php';
		}
		return $template;
	}
);

add_filter(
	'archive_template',
	static function ( string $template ): string {
		if ( fcs_child_people_active() && is_post_type_archive( 'ctc_person' ) ) {
			return get_stylesheet_directory() . '/templates/staff-archive.php';
		}
		return $template;
	}
);
