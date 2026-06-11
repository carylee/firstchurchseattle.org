<?php
/**
 * First Church MCP Abilities — content health + URL resolution.
 *
 * Read-only site-introspection that turns the agent into a maintenance
 * assistant:
 *  - content-health runs cheap WP_Query audits (upcoming events with no
 *    featured image, announcements expiring soon / already expired, stale
 *    drafts, pages untouched for a year) and returns grouped findings;
 *  - resolve-url answers "what's behind this path?" — a published post/page,
 *    an enabled Redirection rule, or nothing — the lookup agents constantly
 *    need for internal linking and redirect work.
 *
 * The date math and path normalization are pure functions (no WordPress) so
 * they're unit-tested in firstchurch-mcp/; the queries are thin WP glue.
 *
 * Loaded by ../firstchurch-mcp-abilities.php (WordPress does not auto-load
 * mu-plugin subdirectories). Procedural, global namespace, no autoloader —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------------------
 * Pure helpers (unit-tested; no WordPress).
 * ------------------------------------------------------------------------- */

/** $base (YYYY-MM-DD) shifted by $days (may be negative), as YYYY-MM-DD. */
function fcmcp_date_offset( int $days, string $base ): string {
	$ts = strtotime( $base );
	if ( false === $ts ) {
		$ts = time();
	}
	return gmdate( 'Y-m-d', $ts + $days * 86400 );
}

/**
 * Reduce a path or full URL to a clean, leading-slash site path (no trailing
 * slash except root, no scheme/host/query/fragment). A same-site origin is
 * stripped; for any absolute URL we keep just the path.
 */
function fcmcp_normalize_path( string $input, string $home_url ): string {
	$input = trim( $input );
	if ( '' === $input ) {
		return '/';
	}
	$parts = parse_url( $input );
	$path  = is_array( $parts ) && isset( $parts['path'] ) ? $parts['path'] : $input;
	if ( ! is_array( $parts ) ) {
		$path = $input;
	}
	$path = '/' . ltrim( (string) $path, '/' );
	$path = preg_replace( '#/+#', '/', $path );
	return '/' === $path ? '/' : rtrim( $path, '/' );
}

/* ----------------------------------------------------------------------------
 * content-health.
 * ------------------------------------------------------------------------- */

const FCMCP_HEALTH_CHECKS = array(
	'events_missing_image',
	'announcements_expiring',
	'announcements_expired',
	'stale_drafts',
	'stale_pages',
);

/** Compact shape for one finding. */
function fcmcp_health_item( WP_Post $p, array $extra = array() ): array {
	return array_merge(
		array(
			'id'       => $p->ID,
			'type'     => $p->post_type,
			'title'    => get_the_title( $p ),
			'status'   => $p->post_status,
			'modified' => get_post_modified_time( 'Y-m-d', false, $p ),
			'edit_url' => (string) get_edit_post_link( $p->ID, 'raw' ),
		),
		$extra
	);
}

/** Run one named check; returns a list of finding items. */
function fcmcp_health_check( string $check, array $ctx ): array {
	$today = $ctx['today'];
	$limit = $ctx['limit'];
	$base  = array( 'posts_per_page' => $limit, 'no_found_rows' => true );

	switch ( $check ) {
		case 'events_missing_image':
			$q = new WP_Query( $base + array(
				'post_type'   => 'ctc_event',
				'post_status' => 'publish',
				'orderby'     => 'meta_value',
				'meta_key'    => '_ctc_event_start_date',
				'order'       => 'ASC',
				'meta_query'  => array(
					'relation' => 'AND',
					array( 'key' => '_ctc_event_start_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
					array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ),
				),
			) );
			return array_map(
				static fn ( $p ) => fcmcp_health_item( $p, array( 'start_date' => (string) get_post_meta( $p->ID, '_ctc_event_start_date', true ) ) ),
				$q->posts
			);

		case 'announcements_expiring':
			$q = new WP_Query( $base + array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'cat'         => fcmcp_announce_cat_id(),
				'meta_query'  => array(
					array(
						'key'     => 'fcs_expires',
						'value'   => array( $today, fcmcp_date_offset( $ctx['expiring_days'], $today ) ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			) );
			return array_map(
				static fn ( $p ) => fcmcp_health_item( $p, array( 'expires' => (string) get_post_meta( $p->ID, 'fcs_expires', true ) ) ),
				$q->posts
			);

		case 'announcements_expired':
			$q = new WP_Query( $base + array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'cat'         => fcmcp_announce_cat_id(),
				'meta_query'  => array(
					'relation' => 'AND',
					array( 'key' => 'fcs_expires', 'value' => '', 'compare' => '!=' ),
					array( 'key' => 'fcs_expires', 'value' => $today, 'compare' => '<', 'type' => 'DATE' ),
				),
			) );
			return array_map(
				static fn ( $p ) => fcmcp_health_item( $p, array( 'expires' => (string) get_post_meta( $p->ID, 'fcs_expires', true ) ) ),
				$q->posts
			);

		case 'stale_drafts':
			$q = new WP_Query( $base + array(
				'post_type'   => array( 'ctc_event', 'post', 'page', 'enews_issue' ),
				'post_status' => array( 'draft', 'pending' ),
				'orderby'     => 'modified',
				'order'       => 'ASC',
				'date_query'  => array(
					array( 'column' => 'post_modified', 'before' => fcmcp_date_offset( -$ctx['stale_draft_days'], $today ) ),
				),
			) );
			return array_map( 'fcmcp_health_item', $q->posts );

		case 'stale_pages':
			$q = new WP_Query( $base + array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'orderby'     => 'modified',
				'order'       => 'ASC',
				'date_query'  => array(
					array( 'column' => 'post_modified', 'before' => fcmcp_date_offset( -$ctx['stale_page_days'], $today ) ),
				),
			) );
			return array_map( 'fcmcp_health_item', $q->posts );
	}
	return array();
}

function fcmcp_content_health( $input = array() ) {
	$ctx = array(
		'today'            => current_time( 'Y-m-d' ),
		'limit'            => max( 1, min( 100, (int) ( $input['limit'] ?? 25 ) ) ),
		'expiring_days'    => max( 0, (int) ( $input['expiring_days'] ?? 7 ) ),
		'stale_draft_days' => max( 1, (int) ( $input['stale_draft_days'] ?? 30 ) ),
		'stale_page_days'  => max( 1, (int) ( $input['stale_page_days'] ?? 365 ) ),
	);
	$checks = ( ! empty( $input['checks'] ) && is_array( $input['checks'] ) )
		? array_values( array_intersect( FCMCP_HEALTH_CHECKS, $input['checks'] ) )
		: FCMCP_HEALTH_CHECKS;

	$findings = array();
	$counts   = array();
	$total    = 0;
	foreach ( $checks as $check ) {
		$items             = fcmcp_health_check( $check, $ctx );
		$findings[ $check ] = $items;
		$counts[ $check ]   = count( $items );
		$total             += count( $items );
	}
	$counts['total'] = $total;

	return array(
		'generated_at' => current_time( 'mysql', true ),
		'counts'       => $counts,
		'findings'     => $findings,
	);
}

/* ----------------------------------------------------------------------------
 * resolve-url.
 * ------------------------------------------------------------------------- */

/** Find an enabled Redirection rule whose source matches $path exactly. */
function fcmcp_resolve_redirect( string $path ) {
	if ( ! function_exists( 'fcmcp_redirect_available' ) || ! fcmcp_redirect_available() ) {
		return null;
	}
	global $wpdb;
	$table       = $wpdb->prefix . 'redirection_items';
	$candidates  = array_values( array_unique( array( $path, rtrim( $path, '/' ), '/' === $path ? '/' : $path . '/' ) ) );
	$placeholders = implode( ',', array_fill( 0, count( $candidates ), '%s' ) );
	$sql = "SELECT id FROM {$table} WHERE url IN ($placeholders) AND status = 'enabled' ORDER BY position, id LIMIT 1";
	$id  = $wpdb->get_var( $wpdb->prepare( $sql, $candidates ) );
	if ( ! $id ) {
		return null;
	}
	$item = Red_Item::get_by_id( (int) $id );
	return $item ? fcmcp_redirect_to_array( $item ) : null;
}

function fcmcp_resolve_url( $input ) {
	$raw = (string) ( $input['path'] ?? '' );
	if ( '' === trim( $raw ) ) {
		return new WP_Error( 'bad_input', 'path is required.' );
	}
	$path = fcmcp_normalize_path( $raw, home_url() );

	$post    = null;
	$post_id = url_to_postid( home_url( $path ) );
	if ( $post_id ) {
		$p = get_post( $post_id );
		if ( $p ) {
			$post = array(
				'id'       => $p->ID,
				'type'     => $p->post_type,
				'title'    => get_the_title( $p ),
				'status'   => $p->post_status,
				'url'      => (string) get_permalink( $p ),
				'edit_url' => (string) get_edit_post_link( $p->ID, 'raw' ),
			);
		}
	}

	$redirect = fcmcp_resolve_redirect( $path );
	$resolved = $redirect ? 'redirect' : ( $post ? 'post' : 'none' );

	return array(
		'input'    => $raw,
		'path'     => $path,
		'resolved' => $resolved,
		'post'     => $post,
		'redirect' => $redirect,
	);
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
		$read_meta  = array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) );
		$can_edit   = static function () { return current_user_can( 'edit_posts' ); };

		wp_register_ability(
			'firstchurch/content-health',
			array(
				'label'               => 'Content health audit',
				'description'         => 'Maintenance scan: upcoming events with no featured image, announcements expiring soon or already expired, stale drafts/pending, and pages untouched for a year. Returns findings grouped by check with counts. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'checks'           => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string', 'enum' => FCMCP_HEALTH_CHECKS ),
							'description' => 'Subset of checks to run. Omit for all.',
						),
						'expiring_days'    => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 90, 'default' => 7, 'description' => 'Window for "announcements expiring".' ),
						'stale_draft_days' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => 30, 'description' => 'Drafts/pending unmodified this long count as stale.' ),
						'stale_page_days'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 3650, 'default' => 365, 'description' => 'Published pages unmodified this long count as stale.' ),
						'limit'            => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25, 'description' => 'Max findings per check.' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_content_health',
				'permission_callback' => $can_edit,
				'meta'                => $read_meta,
			)
		);

		wp_register_ability(
			'firstchurch/resolve-url',
			array(
				'label'               => 'Resolve a URL',
				'description'         => 'Given a site path or full URL, return what is behind it: a published post/page (id, type, title, status, edit_url), an enabled redirect (source → target), or nothing. For internal linking and redirect work. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'path' => array( 'type' => 'string', 'description' => 'A path like /about/staff or a full https URL on this site.' ),
					),
					'required'   => array( 'path' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_resolve_url',
				'permission_callback' => $can_edit,
				'meta'                => $read_meta,
			)
		);
	}
);
