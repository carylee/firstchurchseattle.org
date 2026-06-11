<?php
/**
 * First Church MCP Abilities — bootstrap.
 *
 * Constants, the FCMCP_DIRECT_TOOLS promotion filter, the mcp_editor capability-scoping filter, and the ability category registration.
 *
 * Loaded by ../firstchurch-mcp-abilities.php (WordPress does not auto-load
 * mu-plugin subdirectories). Procedural, global namespace, no autoloader —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

const FCMCP_ANNOUNCE_SLUG = 'announcements';
const FCMCP_WRITER_ROLE   = 'mcp_editor';

/* ----------------------------------------------------------------------------
 * Promote a curated subset to first-class MCP tools.
 *
 * All ~46 abilities are reachable through the adapter's default server via the
 * discover/get-info/execute-ability meta-tools (they're flagged mcp.public).
 * That indirection is fine for the long tail, but the high-frequency verbs are
 * better as native tools the client sees directly in tools/list — no per-session
 * discovery dance. The adapter exposes a `mcp_adapter_default_server_config`
 * filter (applied in DefaultServerFactory::create during the mcp_adapter_init
 * action), whose `tools` array accepts ability names; each is wrapped via
 * McpTool::fromAbility. We append the curated set to that array, so these surface
 * as first-class tools on the SAME endpoint while the rest stay behind execute.
 *
 * To promote/demote a tool, just edit FCMCP_DIRECT_TOOLS. Anything omitted is
 * still callable via the meta-tools. (Registered top-level so the filter is in
 * place before mcp_adapter_init fires.)
 * ------------------------------------------------------------------------- */
const FCMCP_DIRECT_TOOLS = array(
	// Events
	'firstchurch/search-events', 'firstchurch/create-event', 'firstchurch/update-event', 'firstchurch/set-event-status',
	// Sermons
	'firstchurch/search-sermons', 'firstchurch/create-sermon', 'firstchurch/update-sermon', 'firstchurch/set-sermon-status',
	// Announcements
	'firstchurch/list-announcements', 'firstchurch/get-announcement', 'firstchurch/create-announcement', 'firstchurch/update-announcement', 'firstchurch/set-announcement-status',
	// Posts
	'firstchurch/search-posts', 'firstchurch/create-post', 'firstchurch/update-post', 'firstchurch/set-post-status',
	// Pages
	'firstchurch/search-pages', 'firstchurch/create-page', 'firstchurch/update-page', 'firstchurch/set-page-status',
	// Redirects
	'firstchurch/search-redirects', 'firstchurch/create-redirect', 'firstchurch/update-redirect', 'firstchurch/set-redirect-enabled',
	// Navigation menus
	'firstchurch/list-menus', 'firstchurch/get-menu', 'firstchurch/add-menu-item', 'firstchurch/update-menu-item', 'firstchurch/remove-menu-item', 'firstchurch/reorder-menu',
	// E-News (firstchurch-enews plugin — registered there, promoted here)
	'firstchurch/list-enews', 'firstchurch/get-enews', 'firstchurch/create-enews', 'firstchurch/update-enews', 'firstchurch/set-enews-status', 'firstchurch/preview-enews',
	// Safety rails
	'firstchurch/restore', 'firstchurch/list-trash', 'firstchurch/audit-log',
	// Dashboard
	'firstchurch/review-queue',
);

add_filter(
	'mcp_adapter_default_server_config',
	static function ( $config ) {
		if ( ! is_array( $config ) ) {
			return $config;
		}
		$existing        = isset( $config['tools'] ) && is_array( $config['tools'] ) ? $config['tools'] : array();
		$config['tools'] = array_values( array_unique( array_merge( $existing, FCMCP_DIRECT_TOOLS ) ) );
		return $config;
	}
);

/* ----------------------------------------------------------------------------
 * Capability scoping: the mcp_editor role may only edit/delete/PUBLISH the
 * managed types (events, sermons, posts, pages — see fcmcp_is_managed_post).
 * Defense in depth for the app-password credential: even with publish_posts/
 * publish_pages granted, this keeps it away from attachments, users, settings,
 * and other CPTs.
 * ------------------------------------------------------------------------- */
add_filter(
	'map_meta_cap',
	static function ( $caps, $cap, $user_id, $args ) {
		$gated = array( 'edit_post', 'delete_post', 'publish_post' );
		if ( ! in_array( $cap, $gated, true ) ) {
			return $caps;
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( FCMCP_WRITER_ROLE, (array) $user->roles, true ) ) {
			return $caps;
		}
		$post_id = $args[0] ?? 0;
		if ( $post_id && ! fcmcp_is_managed_post( $post_id ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	},
	10,
	4
);

/* ----------------------------------------------------------------------------
 * Ability category
 * ------------------------------------------------------------------------- */
add_action(
	'wp_abilities_api_categories_init',
	static function () {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'firstchurch',
				array(
					'label'       => 'First Church',
					'description' => 'Events and announcements management for First Church Seattle.',
				)
			);
		}
	}
);
