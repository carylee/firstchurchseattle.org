<?php
/**
 * Decluttered chrome for the comms_editor role. A comms_editor lacks
 * manage_options, so most sprawl (Settings, Connectors, Plugins, Users, Site
 * Kit, Yoast settings, UpdraftPlus, theme tools) is already hidden by core. This
 * removes the remaining editor-visible menus the coordinator doesn't own — the
 * third-party content types from Church Theme Content and comment moderation —
 * leaving a focused set: Comms Desk, Events, Posts (Announcements), E-News,
 * Carousel, Intake, Media, and Tools → Church Voice.
 *
 * @package FirstChurch\CommsDesk
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function (): void {
		$user = wp_get_current_user();
		if ( ! ( $user instanceof WP_User ) || ! in_array( FCCD_ROLE, (array) $user->roles, true ) ) {
			return;
		}
		$hide = array(
			'edit.php?post_type=ctc_sermon',   // Sermons (Church Theme Content)
			'edit.php?post_type=ctc_location', // Locations
			'edit.php?post_type=ctc_person',   // People
			'edit.php?post_type=ctc_event',    // legacy Events (being phased out)
			'edit-comments.php',               // Comments
		);
		foreach ( $hide as $slug ) {
			remove_menu_page( $slug );
		}
	},
	999
);
