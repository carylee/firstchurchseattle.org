<?php
/**
 * The human `comms_editor` role + its guardrails.
 *
 * Distinct from `mcp_editor` (an app-password service account for agents). Same
 * managed-content scope, but for a person who logs in: events, announcements
 * (posts), pages, e-news, carousel cards, and media. Scoped in depth by a
 * map_meta_cap filter mirroring the mcp_editor one, and landed on the Comms Desk
 * after login.
 *
 * @package FirstChurch\CommsDesk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capabilities for the comms role — the managed content types + media upload.
 * Mirrors the mcp_editor EDITOR_CAPS (ops/scripts/setup-roles.sh) minus the
 * narrow fcmcp_manage_* abilities (redirects/menus aren't in the v1 surface).
 *
 * @return array<string,bool>
 */
function fccd_role_caps(): array {
	return array(
		'read'                     => true,
		'edit_posts'               => true,
		'edit_others_posts'        => true,
		'edit_published_posts'     => true,
		'publish_posts'            => true,
		'delete_posts'             => true,
		'delete_others_posts'      => true,
		'delete_published_posts'   => true,
		'edit_pages'               => true,
		'edit_others_pages'        => true,
		'edit_published_pages'     => true,
		'publish_pages'            => true,
		'delete_pages'             => true,
		'delete_others_pages'      => true,
		'delete_published_pages'   => true,
		'upload_files'             => true,
	);
}

/** Create or refresh the comms_editor role (idempotent; self-heals on update). */
function fccd_ensure_role(): void {
	$role = get_role( FCCD_ROLE );
	if ( ! $role ) {
		add_role( FCCD_ROLE, 'Comms Editor', fccd_role_caps() );
		return;
	}
	foreach ( fccd_role_caps() as $cap => $grant ) {
		$role->add_cap( $cap );
	}
}
add_action( 'init', 'fccd_ensure_role' );

/**
 * Defense in depth: a comms_editor may only edit/delete/publish the *managed*
 * content types — never attachments, users, settings, or other CPTs. Mirrors the
 * mcp_editor scoping (firstchurch-mcp-abilities/bootstrap.php) and reuses
 * fcmcp_is_managed_post when the mu-plugin is present.
 */
add_filter(
	'map_meta_cap',
	static function ( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'publish_post' ), true ) ) {
			return $caps;
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( FCCD_ROLE, (array) $user->roles, true ) ) {
			return $caps;
		}
		$post_id = $args[0] ?? 0;
		if ( $post_id && function_exists( 'fcmcp_is_managed_post' ) && ! fcmcp_is_managed_post( (int) $post_id ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	},
	10,
	4
);

/** Land comms editors on the Comms Desk after login (their home base). */
add_filter(
	'login_redirect',
	static function ( $redirect_to, $requested, $user ) {
		if ( $user instanceof WP_User && in_array( FCCD_ROLE, (array) $user->roles, true ) ) {
			return admin_url( 'admin.php?page=' . FCCD_SLUG );
		}
		return $redirect_to;
	},
	10,
	3
);
