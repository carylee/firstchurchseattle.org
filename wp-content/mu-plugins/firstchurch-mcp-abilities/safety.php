<?php
/**
 * First Church MCP Abilities — safety rails.
 *
 * Accountability + rollback for autonomous writes:
 *  - an append-only audit trail (who/what/when) of every lifecycle change to a
 *    managed post, captured from core's transition_post_status / before_delete_post
 *    hooks so it covers BOTH MCP and human edits;
 *  - restore (untrash) as the one-call inverse of the trash-* abilities;
 *  - list-trash so an agent can find what to restore.
 *
 * The audit store is a bounded ring buffer in a single (non-autoloaded) option —
 * no table, no migration. The ring-buffer, classification, and filter logic are
 * pure functions (no WordPress) so they're unit-tested in firstchurch-mcp/.
 *
 * Loaded by ../firstchurch-mcp-abilities.php (WordPress does not auto-load
 * mu-plugin subdirectories). Procedural, global namespace, no autoloader —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

const FCMCP_AUDIT_OPTION = 'fcmcp_audit_log';
const FCMCP_AUDIT_MAX    = 250;

/* ----------------------------------------------------------------------------
 * Pure helpers (unit-tested; no WordPress).
 * ------------------------------------------------------------------------- */

/** Prepend an entry (newest-first) and cap the log to $max entries. */
function fcmcp_audit_append( array $log, array $entry, int $max ): array {
	array_unshift( $log, $entry );
	return count( $log ) > $max ? array_slice( $log, 0, $max ) : $log;
}

/** Map a post-status transition to a human action label. */
function fcmcp_audit_classify( string $old, string $new ): string {
	if ( 'trash' === $new ) {
		return 'trashed';
	}
	if ( 'trash' === $old ) {
		return 'restored';
	}
	if ( in_array( $old, array( 'new', 'auto-draft', '' ), true ) ) {
		return 'created';
	}
	if ( $old === $new ) {
		return 'updated';
	}
	return "status: {$old} \u{2192} {$new}";
}

/** Newest-first slice of the log, optionally filtered to one post id. */
function fcmcp_audit_filter( array $log, int $post_id, int $limit ): array {
	if ( $post_id > 0 ) {
		$log = array_values( array_filter( $log, static fn ( $e ) => (int) ( $e['id'] ?? 0 ) === $post_id ) );
	}
	return array_slice( $log, 0, max( 1, $limit ) );
}

/* ----------------------------------------------------------------------------
 * Audit recording (WordPress glue).
 * ------------------------------------------------------------------------- */

/** Append one audit entry for $post under $action. */
function fcmcp_audit_record( string $action, WP_Post $post ): void {
	$user  = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
	$entry = array(
		'time'    => current_time( 'mysql', true ), // GMT
		'user'    => ( $user && $user->ID ) ? $user->user_login : '(system)',
		'user_id' => $user ? (int) $user->ID : 0,
		'action'  => $action,
		'id'      => (int) $post->ID,
		'type'    => $post->post_type,
		'title'   => get_the_title( $post ),
	);
	$log = get_option( FCMCP_AUDIT_OPTION, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	update_option( FCMCP_AUDIT_OPTION, fcmcp_audit_append( $log, $entry, FCMCP_AUDIT_MAX ), false );
}

add_action(
	'transition_post_status',
	static function ( $new, $old, $post ) {
		if ( ! ( $post instanceof WP_Post ) || ! fcmcp_is_managed_post( $post ) ) {
			return;
		}
		if ( 'auto-draft' === $new ) {
			return; // the empty editor draft — noise, not an edit
		}
		fcmcp_audit_record( fcmcp_audit_classify( (string) $old, (string) $new ), $post );
	},
	10,
	3
);

add_action(
	'before_delete_post',
	static function ( $post_id, $post = null ) {
		$post = ( $post instanceof WP_Post ) ? $post : get_post( $post_id );
		if ( ! $post || ! fcmcp_is_managed_post( $post ) ) {
			return;
		}
		fcmcp_audit_record( 'deleted', $post );
	},
	10,
	2
);

/* ----------------------------------------------------------------------------
 * Callbacks.
 * ------------------------------------------------------------------------- */

/** Restore a trashed managed item to its previous status. */
function fcmcp_untrash( int $id ) {
	$post = get_post( $id );
	if ( ! $post || ! fcmcp_is_managed_post( $post ) ) {
		return new WP_Error( 'not_found', 'Item not found or not a managed type.' );
	}
	if ( 'trash' !== $post->post_status ) {
		return new WP_Error( 'not_trashed', 'Item is not in the Trash.' );
	}
	if ( ! wp_untrash_post( $id ) ) {
		return new WP_Error( 'untrash_failed', 'Could not restore the item.' );
	}
	$restored = get_post( $id );
	return array(
		'id'     => $id,
		'type'   => $post->post_type,
		'status' => $restored ? $restored->post_status : 'draft',
	);
}

/** List trashed managed items across every managed type, newest-changed first. */
function fcmcp_list_trash( $input = array() ) {
	$limit = max( 1, min( 100, (int) ( $input['limit'] ?? 25 ) ) );
	$types = array(
		'ctc_event'   => 'event',
		'ctc_sermon'  => 'sermon',
		'post'        => 'post',
		'page'        => 'page',
		'enews_issue' => 'enews',
	);
	$q = new WP_Query(
		array(
			'post_type'      => array_keys( $types ),
			'post_status'    => 'trash',
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		)
	);
	$items = array();
	foreach ( $q->posts as $p ) {
		$items[] = array(
			'id'              => $p->ID,
			'type'            => $types[ $p->post_type ] ?? $p->post_type,
			'title'           => get_the_title( $p ),
			'previous_status' => (string) get_post_meta( $p->ID, '_wp_trash_meta_status', true ),
			'trashed'         => get_post_modified_time( 'Y-m-d H:i', false, $p ),
		);
	}
	return array( 'count' => count( $items ), 'items' => $items );
}

/** Read recent audit entries, optionally filtered to one post id. */
function fcmcp_audit_log_read( $input = array() ) {
	$log = get_option( FCMCP_AUDIT_OPTION, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$entries = fcmcp_audit_filter(
		$log,
		isset( $input['post_id'] ) ? (int) $input['post_id'] : 0,
		max( 1, min( 200, (int) ( $input['limit'] ?? 25 ) ) )
	);
	return array( 'count' => count( $entries ), 'entries' => $entries );
}

/* ----------------------------------------------------------------------------
 * Abilities.
 * ------------------------------------------------------------------------- */

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$mcp_public = array( 'mcp' => array( 'public' => true ) );

		wp_register_ability(
			'firstchurch/restore',
			array(
				'label'               => 'Restore from trash',
				'description'         => 'Restore a trashed managed item (event, sermon, post, page, e-news) to its previous status — the one-call inverse of the trash abilities.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_untrash( (int) $input['id'] );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'delete_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/list-trash',
			array(
				'label'               => 'List trash',
				'description'         => 'List trashed managed items (event, sermon, post, page, e-news), most recently trashed first, with their previous status — find candidates to restore. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25 ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_list_trash',
				'permission_callback' => static function () { return current_user_can( 'edit_posts' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/audit-log',
			array(
				'label'               => 'Audit log',
				'description'         => 'Recent who/what/when trail of changes to managed content (created/updated/published/trashed/restored/deleted), newest first. Pass post_id to scope it to one item. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Only entries for this post id.' ),
						'limit'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 25 ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_audit_log_read',
				'permission_callback' => static function () { return current_user_can( 'edit_posts' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);
	}
);
