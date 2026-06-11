<?php
/**
 * First Church MCP Abilities — redirects.
 *
 * CRUD over the Redirection plugin's Red_Item model (not raw SQL — so action_data
 * serialization, source flags, group-cache flushing, and position are handled by
 * the plugin), gated by fcmcp_manage_redirects. The /enews/latest cron in ops/bin/
 * writes raw SQL on purpose; that is a separate, narrow exception.
 *
 * Loaded by ../firstchurch-mcp-abilities.php (WordPress does not auto-load
 * mu-plugin subdirectories). Procedural, global namespace, no autoloader —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$mcp_public    = array( 'mcp' => array( 'public' => true ) );
		$can_read      = static function () { return current_user_can( 'read' ); };
		$can_redirects = static function () { return current_user_can( 'fcmcp_manage_redirects' ); };

		/* ---- REDIRECTS (Redirection plugin) ---- */

		wp_register_ability(
			'firstchurch/search-redirects',
			array(
				'label'               => 'Search redirects',
				'description'         => 'List/search redirect rules from the Redirection plugin. Optional query matches the source path, target, or title (substring); optional group_id filters by group (1 = "Redirections", 2 = auto "Modified Posts"). Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'query'    => array( 'type' => 'string', 'description' => 'Substring to match against source path, target, or title.' ),
						'group_id' => array( 'type' => 'integer' ),
						'limit'    => array( 'type' => 'integer', 'default' => 50 ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_redirects',
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-redirect',
			array(
				'label'               => 'Get redirect',
				'description'         => 'Get a single redirect rule by ID. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_get_redirect',
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/list-redirect-groups',
			array(
				'label'               => 'List redirect groups',
				'description'         => 'List the Redirection groups (id + name). Use to pick a group_id when creating a redirect. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_list_redirect_groups',
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/create-redirect',
			array(
				'label'               => 'Create redirect',
				'description'         => 'Create a redirect rule. source is the path to match (e.g. "/old-page"). For action_type "url" (the default) a target URL is required and action_code defaults to 301 (permanent; use 302 for temporary). action_type "error" returns an HTTP error (action_code 404 or 410) and needs no target. regex=true treats the source as a regular expression (advanced). Defaults to group 1 ("Redirections") and enabled.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source'      => array( 'type' => 'string', 'description' => 'Source path to match, e.g. "/old-page".' ),
						'target'      => array( 'type' => 'string', 'description' => 'Destination URL or path (required for action_type "url").' ),
						'action_type' => array( 'type' => 'string', 'enum' => array( 'url', 'error', 'pass', 'nothing' ), 'default' => 'url' ),
						'action_code' => array( 'type' => 'integer', 'description' => 'HTTP code. url: 301/302/307; error: 404/410. Defaults 301 (url) or 404 (error).' ),
						'group_id'    => array( 'type' => 'integer', 'default' => 1 ),
						'regex'       => array( 'type' => 'boolean', 'default' => false ),
						'title'       => array( 'type' => 'string' ),
						'enabled'     => array( 'type' => 'boolean', 'default' => true ),
					),
					'required'             => array( 'source' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_redirect',
				'permission_callback' => $can_redirects,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-redirect',
			array(
				'label'               => 'Update redirect',
				'description'         => 'Update an existing redirect by ID. Only the fields you pass change; the rest are preserved. Does not change enabled/disabled state (use set-redirect-enabled).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'          => array( 'type' => 'integer' ),
						'source'      => array( 'type' => 'string' ),
						'target'      => array( 'type' => 'string' ),
						'action_type' => array( 'type' => 'string', 'enum' => array( 'url', 'error', 'pass', 'nothing' ) ),
						'action_code' => array( 'type' => 'integer' ),
						'group_id'    => array( 'type' => 'integer' ),
						'regex'       => array( 'type' => 'boolean' ),
						'title'       => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_redirect',
				'permission_callback' => $can_redirects,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-redirect-enabled',
			array(
				'label'               => 'Enable/disable redirect',
				'description'         => 'Enable or disable a redirect by ID. Disabling is the reversible alternative to deleting.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'      => array( 'type' => 'integer' ),
						'enabled' => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'id', 'enabled' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_set_redirect_enabled',
				'permission_callback' => $can_redirects,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/delete-redirect',
			array(
				'label'               => 'Delete redirect',
				'description'         => 'Permanently delete a redirect by ID. Not recoverable — prefer set-redirect-enabled (disable) unless you are sure.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_delete_redirect',
				'permission_callback' => $can_redirects,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);
	}
);

function fcmcp_redirect_available(): bool {
	return class_exists( 'Red_Item' );
}

/**
 * Flatten a Red_Item into a compact, stable shape for MCP output.
 *
 * @param Red_Item $item
 */
function fcmcp_redirect_to_array( $item ): array {
	$j  = $item->to_json();
	$ad = $j['action_data'];
	$target = null;
	if ( is_array( $ad ) && isset( $ad['url'] ) ) {
		$target = $ad['url'];
	} elseif ( is_string( $ad ) && '' !== $ad ) {
		$target = $ad;
	}
	return array(
		'id'          => (int) $j['id'],
		'source'      => $j['url'],
		'target'      => $target,
		'action_type' => $j['action_type'],
		'action_code' => (int) $j['action_code'],
		'match_type'  => $j['match_type'],
		'regex'       => (bool) $j['regex'],
		'group_id'    => (int) $j['group_id'],
		'title'       => $j['title'],
		'hits'        => (int) $j['hits'],
		'enabled'     => (bool) $j['enabled'],
	);
}

function fcmcp_search_redirects( $input = array() ) {
	if ( ! fcmcp_redirect_available() ) {
		return new WP_Error( 'no_redirection', 'The Redirection plugin is not active.' );
	}
	global $wpdb;
	$table = $wpdb->prefix . 'redirection_items';
	$limit = max( 1, min( 200, (int) ( $input['limit'] ?? 50 ) ) );

	$where = array( '1=1' );
	$args  = array();
	if ( ! empty( $input['query'] ) ) {
		$like    = '%' . $wpdb->esc_like( (string) $input['query'] ) . '%';
		$where[] = '(url LIKE %s OR action_data LIKE %s OR title LIKE %s)';
		$args[]  = $like;
		$args[]  = $like;
		$args[]  = $like;
	}
	if ( isset( $input['group_id'] ) ) {
		$where[] = 'group_id = %d';
		$args[]  = (int) $input['group_id'];
	}

	$sql = "SELECT id FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY group_id, position, id LIMIT ' . $limit;
	$ids = $args ? $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_col( $sql );

	$out = array();
	foreach ( $ids as $id ) {
		$item = Red_Item::get_by_id( (int) $id );
		if ( $item ) {
			$out[] = fcmcp_redirect_to_array( $item );
		}
	}
	return array( 'count' => count( $out ), 'redirects' => $out );
}

function fcmcp_get_redirect( $input ) {
	if ( ! fcmcp_redirect_available() ) {
		return new WP_Error( 'no_redirection', 'The Redirection plugin is not active.' );
	}
	$item = Red_Item::get_by_id( (int) ( $input['id'] ?? 0 ) );
	if ( ! $item ) {
		return new WP_Error( 'not_found', 'Redirect not found.' );
	}
	return fcmcp_redirect_to_array( $item );
}

function fcmcp_list_redirect_groups( $input = array() ) {
	if ( ! class_exists( 'Red_Group' ) ) {
		return new WP_Error( 'no_redirection', 'The Redirection plugin is not active.' );
	}
	$out = array();
	foreach ( Red_Group::get_all() as $g ) {
		// Red_Group::get_all() returns raw DB rows (associative arrays).
		$out[] = array( 'id' => (int) $g['id'], 'name' => $g['name'] );
	}
	return array( 'groups' => $out );
}

function fcmcp_create_redirect( $input ) {
	if ( ! fcmcp_redirect_available() ) {
		return new WP_Error( 'no_redirection', 'The Redirection plugin is not active.' );
	}
	$source = isset( $input['source'] ) ? trim( (string) $input['source'] ) : '';
	if ( '' === $source ) {
		return new WP_Error( 'bad_source', 'source is required.' );
	}
	$action_type = isset( $input['action_type'] ) ? (string) $input['action_type'] : 'url';

	$details = array(
		'url'         => $source,
		'match_type'  => 'url',
		'action_type' => $action_type,
		'action_code' => (int) ( $input['action_code'] ?? ( 'url' === $action_type ? 301 : 404 ) ),
		'group_id'    => (int) ( $input['group_id'] ?? 1 ),
		'regex'       => ! empty( $input['regex'] ) ? 1 : 0,
		'title'       => isset( $input['title'] ) ? (string) $input['title'] : null,
		'enabled'     => ! ( isset( $input['enabled'] ) && false === $input['enabled'] ),
	);

	if ( 'url' === $action_type ) {
		$target = isset( $input['target'] ) ? trim( (string) $input['target'] ) : '';
		if ( '' === $target ) {
			return new WP_Error( 'bad_target', 'target is required when action_type is "url".' );
		}
		$details['action_data'] = array( 'url' => $target );
	}

	$item = Red_Item::create( $details );
	if ( is_wp_error( $item ) ) {
		return $item;
	}
	return fcmcp_redirect_to_array( $item );
}

function fcmcp_update_redirect( $input ) {
	if ( ! fcmcp_redirect_available() ) {
		return new WP_Error( 'no_redirection', 'The Redirection plugin is not active.' );
	}
	$id   = (int) ( $input['id'] ?? 0 );
	$item = Red_Item::get_by_id( $id );
	if ( ! $item ) {
		return new WP_Error( 'not_found', 'Redirect not found.' );
	}

	// Start from current values so a partial update doesn't blank other fields.
	$cur     = $item->to_json();
	$cur_ad  = $cur['action_data'];
	$cur_tgt = '';
	if ( is_array( $cur_ad ) && isset( $cur_ad['url'] ) ) {
		$cur_tgt = $cur_ad['url'];
	} elseif ( is_string( $cur_ad ) ) {
		$cur_tgt = $cur_ad;
	}

	$action_type = array_key_exists( 'action_type', $input ) ? (string) $input['action_type'] : $cur['action_type'];

	$details = array(
		'url'         => array_key_exists( 'source', $input ) ? trim( (string) $input['source'] ) : $cur['url'],
		'match_type'  => $cur['match_type'],
		'action_type' => $action_type,
		'action_code' => array_key_exists( 'action_code', $input ) ? (int) $input['action_code'] : (int) $cur['action_code'],
		'group_id'    => array_key_exists( 'group_id', $input ) ? (int) $input['group_id'] : (int) $cur['group_id'],
		'regex'       => array_key_exists( 'regex', $input ) ? ( $input['regex'] ? 1 : 0 ) : ( $cur['regex'] ? 1 : 0 ),
		'title'       => array_key_exists( 'title', $input ) ? (string) $input['title'] : $cur['title'],
	);

	if ( 'url' === $action_type ) {
		$target = array_key_exists( 'target', $input ) ? trim( (string) $input['target'] ) : $cur_tgt;
		$details['action_data'] = array( 'url' => $target );
	}

	$r = $item->update( $details );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return fcmcp_redirect_to_array( Red_Item::get_by_id( $id ) );
}

function fcmcp_set_redirect_enabled( $input ) {
	if ( ! fcmcp_redirect_available() ) {
		return new WP_Error( 'no_redirection', 'The Redirection plugin is not active.' );
	}
	$id   = (int) ( $input['id'] ?? 0 );
	$item = Red_Item::get_by_id( $id );
	if ( ! $item ) {
		return new WP_Error( 'not_found', 'Redirect not found.' );
	}
	if ( ! empty( $input['enabled'] ) ) {
		$item->enable();
	} else {
		$item->disable();
	}
	return fcmcp_redirect_to_array( Red_Item::get_by_id( $id ) );
}

function fcmcp_delete_redirect( $input ) {
	if ( ! fcmcp_redirect_available() ) {
		return new WP_Error( 'no_redirection', 'The Redirection plugin is not active.' );
	}
	$id   = (int) ( $input['id'] ?? 0 );
	$item = Red_Item::get_by_id( $id );
	if ( ! $item ) {
		return new WP_Error( 'not_found', 'Redirect not found.' );
	}
	$item->delete();
	return array( 'id' => $id, 'deleted' => true );
}
