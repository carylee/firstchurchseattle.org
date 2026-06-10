<?php
/**
 * Plugin Name: First Church MCP Abilities
 * Description: Read + draft-first write WordPress Abilities for events (ctc_event), announcements (Announcements-category posts), and sermons (ctc_sermon), exposed to AI via the MCP Adapter. Supports publish/pending workflow, recurrence, featured images, media search/labeling, navigation-menu management, and a review queue.
 * Version:     0.8.0
 * Author:      First Church Seattle
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
 * Helpers
 * ------------------------------------------------------------------------- */

function fcmcp_announce_cat_id(): int {
	$term = get_term_by( 'slug', FCMCP_ANNOUNCE_SLUG, 'category' );
	return $term ? (int) $term->term_id : 0;
}

function fcmcp_sanitize_date( $v ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
}

function fcmcp_sanitize_time( $v ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	return preg_match( '/^\d{2}:\d{2}$/', $v ) ? $v : '';
}

/**
 * Accept a date (YYYY-MM-DD) or datetime (YYYY-MM-DD HH:MM[:SS]) and normalize
 * to 'Y-m-d H:i:s', preserving the wall-clock components. The result is treated
 * as SITE-LOCAL time by the callers (which derive the GMT value separately).
 * Returns '' if the value is empty or unparseable.
 */
function fcmcp_sanitize_datetime( $v ): string {
	$v = is_string( $v ) ? trim( $v ) : '';
	if ( '' === $v ) {
		return '';
	}
	// strtotime() runs under WP's UTC default timezone, so gmdate() round-trips
	// the same wall-clock components we were given (no offset applied here).
	$ts = strtotime( $v );
	return false === $ts ? '' : gmdate( 'Y-m-d H:i:s', $ts );
}

/**
 * If $input carries a publication 'date', stamp it onto a post array as a
 * site-local post_date (+ derived GMT). A past value backdates the post; a
 * future value makes WordPress schedule it (status auto-flips to 'future' and
 * the post auto-publishes at that time via cron). No-op when 'date' is absent.
 *
 * @param array $postarr Post array bound for wp_insert_post/wp_update_post.
 * @param array $input   Ability input.
 * @return array         The (possibly) augmented post array.
 */
function fcmcp_apply_post_date( array $postarr, $input ): array {
	$local = fcmcp_sanitize_datetime( $input['date'] ?? '' );
	if ( '' !== $local ) {
		$postarr['post_date']     = $local;
		$postarr['post_date_gmt'] = get_gmt_from_date( $local );
		$postarr['edit_date']     = true;
	}
	return $postarr;
}

function fcmcp_event_to_array( WP_Post $post ): array {
	return array(
		'id'               => $post->ID,
		'title'            => get_the_title( $post ),
		'status'           => $post->post_status,
		'start_date'       => (string) get_post_meta( $post->ID, '_ctc_event_start_date', true ),
		'end_date'         => (string) get_post_meta( $post->ID, '_ctc_event_end_date', true ),
		'start_time'       => (string) get_post_meta( $post->ID, '_ctc_event_start_time', true ),
		'end_time'         => (string) get_post_meta( $post->ID, '_ctc_event_end_time', true ),
		'time'             => (string) get_post_meta( $post->ID, '_ctc_event_time', true ),
		'venue'            => (string) get_post_meta( $post->ID, '_ctc_event_venue', true ),
		'address'          => (string) get_post_meta( $post->ID, '_ctc_event_address', true ),
		'registration_url' => (string) get_post_meta( $post->ID, '_ctc_event_registration_url', true ),
		'categories'       => wp_get_post_terms( $post->ID, 'ctc_event_category', array( 'fields' => 'slugs' ) ),
		'recurrence'       => fcmcp_recurrence_to_array( $post->ID ),
		'featured_image'   => (string) get_the_post_thumbnail_url( $post, 'full' ),
		'url'              => (string) get_permalink( $post ),
		'edit_url'         => (string) get_edit_post_link( $post->ID, 'raw' ),
	);
}

function fcmcp_post_to_array( WP_Post $post ): array {
	return array(
		'id'         => $post->ID,
		'title'      => get_the_title( $post ),
		'status'     => $post->post_status,
		'date'       => get_post_time( 'Y-m-d', false, $post ),
		'excerpt'    => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
		'categories' => wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'slugs' ) ),
		'featured_image' => (string) get_the_post_thumbnail_url( $post, 'full' ),
		'url'        => (string) get_permalink( $post ),
		'edit_url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
	);
}

/** Apply CTC's combined date/time fields after writing base meta. */
function fcmcp_refresh_event_dates( int $post_id ): void {
	if ( function_exists( 'ctc_update_event_date_time' ) ) {
		ctc_update_event_date_time( $post_id );
	}
}

/** Is this post in scope for the writer role (events + announcements only)? */
function fcmcp_is_managed_post( $post ): bool {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	// Managed types the mcp_editor role may edit/delete. Posts and pages are
	// included intentionally (full content management); attachments and other
	// CPTs (nav menus, blocks, etc.) remain out of scope.
	return in_array( $post->post_type, array( 'ctc_event', 'ctc_sermon', 'post', 'page' ), true );
}

/** JSON Schema fragment for image input (shared by create/update abilities). */
function fcmcp_image_schema(): array {
	return array(
		'image_id'  => array( 'type' => 'integer', 'description' => 'Existing media library attachment ID to use as the featured image.' ),
		'image_url' => array( 'type' => 'string', 'description' => 'URL of an image to download into the media library and set as the featured image.' ),
	);
}

/** JSON Schema fragment for a recurrence rule (shared by event create/update/set-recurrence). */
function fcmcp_recurrence_schema(): array {
	return array(
		'type'                 => 'object',
		'description'          => 'Recurrence rule. Omit or set frequency=none for a one-time event.',
		'properties'           => array(
			'frequency'     => array( 'type' => 'string', 'enum' => array( 'none', 'weekly', 'monthly', 'yearly' ) ),
			'interval'      => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Repeat every N weeks/months (default 1).' ),
			'end_date'      => array( 'type' => 'string', 'description' => 'YYYY-MM-DD when recurrence stops (optional).' ),
			'weekly_days'   => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA' ) ), 'description' => 'Weekly only: which weekdays. Omit to repeat on the start date\'s weekday.' ),
			'monthly_type'  => array( 'type' => 'string', 'enum' => array( 'day', 'week' ), 'description' => 'Monthly only: same day-of-month (day) or specific week(s) (week).' ),
			'monthly_weeks' => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( '1', '2', '3', '4', '5', 'last' ) ), 'description' => 'Monthly week type: which weeks of the month.' ),
		),
		'additionalProperties' => false,
	);
}

/** Set a featured image from a media-library ID or a URL (sideload). Returns attachment ID, null, or WP_Error. */
function fcmcp_set_featured_image( int $post_id, array $input ) {
	if ( ! empty( $input['image_id'] ) ) {
		$att_id = (int) $input['image_id'];
		if ( 'attachment' !== get_post_type( $att_id ) ) {
			return new WP_Error( 'bad_image', 'image_id is not a valid attachment.' );
		}
		set_post_thumbnail( $post_id, $att_id );
		return $att_id;
	}
	if ( ! empty( $input['image_url'] ) ) {
		$url = esc_url_raw( $input['image_url'] );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return new WP_Error( 'bad_image_url', 'image_url must be an http(s) URL.' );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'forbidden', 'Not permitted to upload files.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$name       = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$file_array = array(
			'name'     => $name ? sanitize_file_name( $name ) : 'image.jpg',
			'tmp_name' => $tmp,
		);
		$att_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $att_id ) ) {
			@unlink( $tmp );
			return $att_id;
		}
		set_post_thumbnail( $post_id, (int) $att_id );
		return (int) $att_id;
	}
	return null;
}

/** Write CTC recurrence meta for an event from a recurrence rule array. */
function fcmcp_apply_recurrence( int $post_id, $rec ): void {
	if ( ! is_array( $rec ) ) {
		return;
	}
	$freq = isset( $rec['frequency'] ) ? strtolower( (string) $rec['frequency'] ) : 'none';
	if ( ! in_array( $freq, array( 'none', 'weekly', 'monthly', 'yearly' ), true ) ) {
		$freq = 'none';
	}
	update_post_meta( $post_id, '_ctc_event_recurrence', $freq );
	update_post_meta( $post_id, '_ctc_event_recurrence_end_date', fcmcp_sanitize_date( $rec['end_date'] ?? '' ) );

	if ( 'none' === $freq ) {
		return;
	}

	// Baseline defaults (mirrors Church Content Pro's save-time normalization).
	$interval = max( 1, (int) ( $rec['interval'] ?? 1 ) );
	update_post_meta( $post_id, '_ctc_event_recurrence_weekly_interval', '1' );
	update_post_meta( $post_id, '_ctc_event_recurrence_monthly_interval', '1' );
	update_post_meta( $post_id, '_ctc_event_recurrence_monthly_type', 'day' );

	if ( 'weekly' === $freq ) {
		update_post_meta( $post_id, '_ctc_event_recurrence_weekly_interval', (string) $interval );
		$days = array();
		foreach ( (array) ( $rec['weekly_days'] ?? array() ) as $d ) {
			$d = strtoupper( substr( trim( (string) $d ), 0, 2 ) );
			if ( in_array( $d, array( 'SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA' ), true ) ) {
				$days[] = $d;
			}
		}
		if ( $days ) {
			update_post_meta( $post_id, '_ctc_event_recurrence_weekly_type', 'day' );
			update_post_meta( $post_id, '_ctc_event_recurrence_weekly_day', implode( ',', array_values( array_unique( $days ) ) ) );
		} else {
			update_post_meta( $post_id, '_ctc_event_recurrence_weekly_type', '' );
			update_post_meta( $post_id, '_ctc_event_recurrence_weekly_day', '' );
		}
	}

	if ( 'monthly' === $freq ) {
		update_post_meta( $post_id, '_ctc_event_recurrence_monthly_interval', (string) $interval );
		$mtype = ( isset( $rec['monthly_type'] ) && 'week' === $rec['monthly_type'] ) ? 'week' : 'day';
		update_post_meta( $post_id, '_ctc_event_recurrence_monthly_type', $mtype );
		if ( 'week' === $mtype ) {
			$weeks = array();
			foreach ( (array) ( $rec['monthly_weeks'] ?? array() ) as $w ) {
				$w = strtolower( trim( (string) $w ) );
				if ( in_array( $w, array( '1', '2', '3', '4', '5', 'last' ), true ) ) {
					$weeks[] = $w;
				}
			}
			update_post_meta( $post_id, '_ctc_event_recurrence_monthly_week', implode( ',', array_values( array_unique( $weeks ) ) ) );
		} else {
			update_post_meta( $post_id, '_ctc_event_recurrence_monthly_week', '' );
		}
	}
}

/** Read CTC recurrence meta into a rule array for output. */
function fcmcp_recurrence_to_array( int $post_id ): array {
	$freq = (string) get_post_meta( $post_id, '_ctc_event_recurrence', true );
	$out  = array( 'frequency' => $freq ?: 'none' );
	if ( 'none' === $out['frequency'] || '' === $out['frequency'] ) {
		$out['frequency'] = 'none';
		return $out;
	}
	$out['end_date'] = (string) get_post_meta( $post_id, '_ctc_event_recurrence_end_date', true );
	if ( 'weekly' === $freq ) {
		$out['interval'] = (int) get_post_meta( $post_id, '_ctc_event_recurrence_weekly_interval', true );
		$d               = (string) get_post_meta( $post_id, '_ctc_event_recurrence_weekly_day', true );
		$out['weekly_days'] = '' !== $d ? explode( ',', $d ) : array();
	} elseif ( 'monthly' === $freq ) {
		$out['interval']     = (int) get_post_meta( $post_id, '_ctc_event_recurrence_monthly_interval', true );
		$out['monthly_type'] = (string) get_post_meta( $post_id, '_ctc_event_recurrence_monthly_type', true );
		$w                   = (string) get_post_meta( $post_id, '_ctc_event_recurrence_monthly_week', true );
		$out['monthly_weeks'] = '' !== $w ? explode( ',', $w ) : array();
	}
	return $out;
}

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

/* ----------------------------------------------------------------------------
 * Abilities
 * ------------------------------------------------------------------------- */
add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$mcp_public    = array( 'mcp' => array( 'public' => true ) );
		$can_read      = static function () { return current_user_can( 'read' ); };
		$can_edit      = static function () { return current_user_can( 'edit_posts' ); };
		$can_redirects = static function () { return current_user_can( 'fcmcp_manage_redirects' ); };
		$can_menus     = static function () { return current_user_can( 'fcmcp_manage_menus' ); };

		/* ---- EVENTS: READ ---- */

		wp_register_ability(
			'firstchurch/upcoming-events',
			array(
				'label'               => 'Upcoming events',
				'description'         => 'List upcoming published events, soonest first. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10 ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input = array() ) {
					return fcmcp_search_events( array( 'limit' => $input['limit'] ?? 10 ) );
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/search-events',
			array(
				'label'               => 'Search events',
				'description'         => 'Search events with filters: date range, category slug, free-text, status, and order. Drafts/pending require edit permission. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'from_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD; defaults to today' ),
						'to_date'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD; optional upper bound' ),
						'category'  => array( 'type' => 'string', 'description' => 'ctc_event_category slug' ),
						'search'    => array( 'type' => 'string' ),
						'status'    => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'any' ), 'default' => 'publish' ),
						'order'     => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'default' => 'asc' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_events',
				'permission_callback' => static function ( $input = array() ) {
					$status = $input['status'] ?? 'publish';
					if ( 'publish' !== $status && ! current_user_can( 'edit_posts' ) ) {
						return new WP_Error( 'forbidden', 'Viewing non-published events requires edit permission.' );
					}
					return current_user_can( 'read' );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-event',
			array(
				'label'               => 'Get event',
				'description'         => 'Get full detail for one event by ID. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'ctc_event' !== $post->post_type ) {
						return new WP_Error( 'not_found', 'Event not found.' );
					}
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						return new WP_Error( 'forbidden', 'Not permitted to view this event.' );
					}
					return fcmcp_event_to_array( $post );
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/list-event-categories',
			array(
				'label'               => 'List event categories',
				'description'         => 'List ctc_event_category terms (slug, name, count). Read-only.',
				'category'            => 'firstchurch',
				'execute_callback'    => static function () {
					$terms = get_terms( array( 'taxonomy' => 'ctc_event_category', 'hide_empty' => false ) );
					$out   = array();
					foreach ( $terms as $t ) {
						$out[] = array( 'slug' => $t->slug, 'name' => $t->name, 'count' => (int) $t->count );
					}
					return array( 'categories' => $out );
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		/* ---- EVENTS: WRITE (draft-first) ---- */

		wp_register_ability(
			'firstchurch/create-event',
			array(
				'label'               => 'Create event (draft)',
				'description'         => 'Create a new event as a DRAFT (a human publishes it). Provide title and start_date at minimum.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'            => array( 'type' => 'string' ),
						'description'      => array( 'type' => 'string' ),
						'start_date'       => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
						'end_date'         => array( 'type' => 'string', 'description' => 'YYYY-MM-DD; defaults to start_date' ),
						'start_time'       => array( 'type' => 'string', 'description' => 'HH:MM (24h)' ),
						'end_time'         => array( 'type' => 'string', 'description' => 'HH:MM (24h)' ),
						'time_text'        => array( 'type' => 'string', 'description' => 'Human-readable time, e.g. "10:30 am"' ),
						'venue'            => array( 'type' => 'string' ),
						'address'          => array( 'type' => 'string' ),
						'registration_url' => array( 'type' => 'string' ),
						'category'         => array( 'type' => 'string', 'description' => 'ctc_event_category slug' ),
						'recurrence'       => fcmcp_recurrence_schema(),
						'image_id'         => array( 'type' => 'integer', 'description' => 'Existing media library attachment ID to use as the featured image.' ),
						'image_url'        => array( 'type' => 'string', 'description' => 'URL of an image to download and set as the featured image.' ),
						'date'             => array( 'type' => 'string', 'description' => 'WordPress publication date/time (YYYY-MM-DD or YYYY-MM-DD HH:MM, site local), separate from start_date. Past backdates; future schedules the post to auto-publish then.' ),
						'status'           => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft', 'description' => 'draft (default), pending (queue for approval), or publish (go live now).' ),
					),
					'required'             => array( 'title', 'start_date' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_event',
				'permission_callback' => $can_edit,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-event',
			array(
				'label'               => 'Update event',
				'description'         => 'Update fields of an existing event by ID. Does not change publish status (use set-event-status). Only title/description/date/time/venue/address/registration_url/category may be set.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'               => array( 'type' => 'integer' ),
						'title'            => array( 'type' => 'string' ),
						'description'      => array( 'type' => 'string' ),
						'start_date'       => array( 'type' => 'string' ),
						'end_date'         => array( 'type' => 'string' ),
						'start_time'       => array( 'type' => 'string' ),
						'end_time'         => array( 'type' => 'string' ),
						'time_text'        => array( 'type' => 'string' ),
						'venue'            => array( 'type' => 'string' ),
						'address'          => array( 'type' => 'string' ),
						'registration_url' => array( 'type' => 'string' ),
						'category'         => array( 'type' => 'string' ),
						'recurrence'       => fcmcp_recurrence_schema(),
						'image_id'         => array( 'type' => 'integer', 'description' => 'Existing media library attachment ID to use as the featured image.' ),
						'image_url'        => array( 'type' => 'string', 'description' => 'URL of an image to download and set as the featured image.' ),
						'date'             => array( 'type' => 'string', 'description' => 'WordPress publication date/time (YYYY-MM-DD or YYYY-MM-DD HH:MM, site local), separate from start_date. Past backdates; future schedules the post to auto-publish then.' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_event',
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-event-status',
			array(
				'label'               => 'Set event status',
				'description'         => 'Set an event to draft, pending (queue for approval), or publish (go live now).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
					),
					'required'             => array( 'id', 'status' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_set_status( (int) $input['id'], (string) $input['status'], 'ctc_event' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/trash-event',
			array(
				'label'               => 'Trash event',
				'description'         => 'Move an event to the Trash (recoverable). Does not permanently delete.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_trash( (int) $input['id'], 'ctc_event' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'delete_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);

		/* ---- ANNOUNCEMENTS: READ ---- */

		wp_register_ability(
			'firstchurch/list-announcements',
			array(
				'label'               => 'List announcements',
				'description'         => 'List posts in the Announcements category, newest first. Each item carries a truncated excerpt only — call get-announcement with the id for the full body. Drafts/pending require edit permission. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'      => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'since_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD lower bound' ),
						'search'     => array( 'type' => 'string' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'any' ), 'default' => 'publish' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_list_announcements',
				'permission_callback' => static function ( $input = array() ) {
					$status = $input['status'] ?? 'publish';
					if ( 'publish' !== $status && ! current_user_can( 'edit_posts' ) ) {
						return new WP_Error( 'forbidden', 'Viewing non-published announcements requires edit permission.' );
					}
					return current_user_can( 'read' );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-announcement',
			array(
				'label'               => 'Get announcement',
				'description'         => 'Get one announcement post by ID, including full content. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'post' !== $post->post_type || ! has_category( fcmcp_announce_cat_id(), $post ) ) {
						return new WP_Error( 'not_found', 'Announcement not found.' );
					}
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						return new WP_Error( 'forbidden', 'Not permitted to view this announcement.' );
					}
					$data            = fcmcp_post_to_array( $post );
					$data            = array_merge( $data, fcmcp_announcement_extra( $post ) );
					$data['content'] = apply_filters( 'the_content', $post->post_content );
					return $data;
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		/* ---- ANNOUNCEMENTS: WRITE (draft-first) ---- */

		wp_register_ability(
			'firstchurch/create-announcement',
			array(
				'label'               => 'Create announcement (draft)',
				'description'         => 'Create a new announcement post as a DRAFT in the Announcements category (a human publishes it).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string', 'description' => 'Body (may contain basic HTML).' ),
						'excerpt' => array( 'type' => 'string' ),
						'cta_text' => array( 'type' => 'string', 'description' => 'Call-to-action button label (e.g. "RSVP", "Learn more"). Optional; defaults to "Learn more" when a cta_url is set.' ),
						'cta_url'  => array( 'type' => 'string', 'description' => 'Call-to-action button URL. The button only renders when this is set. Use mailto: for "contact X" asks.' ),
						'weight'   => array( 'type' => 'integer', 'description' => 'Prominence on /engage and the carousel. 0 = normal; 10 floats it to the Featured row; 20 pins it to the top.' ),
						'expires'  => array( 'type' => 'string', 'description' => 'Stop showing on /engage and the carousel after this date (YYYY-MM-DD). The post stays published in the news archive.' ),
						'image_id'  => array( 'type' => 'integer', 'description' => 'Existing media library attachment ID to use as the featured image.' ),
						'image_url' => array( 'type' => 'string', 'description' => 'URL of an image to download and set as the featured image.' ),
						'date'      => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then. Defaults to now.' ),
						'status'    => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft', 'description' => 'draft (default), pending (queue for approval), or publish (go live now).' ),
					),
					'required'             => array( 'title', 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_announcement',
				'permission_callback' => $can_edit,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-announcement',
			array(
				'label'               => 'Update announcement',
				'description'         => 'Update an existing announcement (title/content/excerpt) by ID. Does not change publish status.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'      => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
						'cta_text' => array( 'type' => 'string', 'description' => 'Call-to-action button label.' ),
						'cta_url'  => array( 'type' => 'string', 'description' => 'Call-to-action button URL (button renders only when set).' ),
						'weight'   => array( 'type' => 'integer', 'description' => 'Prominence on /engage and the carousel. 0 = normal; 10 floats it to the Featured row; 20 pins it to the top.' ),
						'expires'  => array( 'type' => 'string', 'description' => 'Stop showing on /engage and the carousel after this date (YYYY-MM-DD). The post stays published in the news archive.' ),
						'image_id'  => array( 'type' => 'integer', 'description' => 'Existing media library attachment ID to use as the featured image.' ),
						'image_url' => array( 'type' => 'string', 'description' => 'URL of an image to download and set as the featured image.' ),
						'date'      => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then.' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_announcement',
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-announcement-status',
			array(
				'label'               => 'Set announcement status',
				'description'         => 'Set an announcement to draft, pending (queue for approval), or publish (go live now).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
					),
					'required'             => array( 'id', 'status' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_set_status( (int) $input['id'], (string) $input['status'], 'post' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/trash-announcement',
			array(
				'label'               => 'Trash announcement',
				'description'         => 'Move an announcement to the Trash (recoverable).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_trash( (int) $input['id'], 'post' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'delete_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);

		/* ---- SHARED: FEATURED IMAGE + RECURRENCE ---- */

		wp_register_ability(
			'firstchurch/set-featured-image',
			array(
				'label'               => 'Set featured image',
				'description'         => 'Set the featured image of an event or announcement, either from an existing media library attachment (image_id) or by downloading an image URL (image_url).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						array( 'id' => array( 'type' => 'integer' ) ),
						fcmcp_image_schema()
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$id = (int) ( $input['id'] ?? 0 );
					if ( ! fcmcp_is_managed_post( $id ) ) {
						return new WP_Error( 'not_found', 'Only events and announcements are supported.' );
					}
					$res = fcmcp_set_featured_image( $id, $input );
					if ( is_wp_error( $res ) ) {
						return $res;
					}
					if ( null === $res ) {
						return new WP_Error( 'no_image', 'Provide image_id or image_url.' );
					}
					return array( 'id' => $id, 'attachment_id' => (int) $res, 'featured_image' => (string) get_the_post_thumbnail_url( $id, 'full' ) );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-event-recurrence',
			array(
				'label'               => 'Set event recurrence',
				'description'         => 'Set or clear the recurrence rule for an event (weekly/monthly/yearly, with interval, days/weeks, and end date). Set frequency=none to make it one-time.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'         => array( 'type' => 'integer' ),
						'recurrence' => fcmcp_recurrence_schema(),
					),
					'required'             => array( 'id', 'recurrence' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$id   = (int) ( $input['id'] ?? 0 );
					$post = get_post( $id );
					if ( ! $post || 'ctc_event' !== $post->post_type ) {
						return new WP_Error( 'not_found', 'Event not found.' );
					}
					fcmcp_apply_recurrence( $id, $input['recurrence'] ?? array() );
					fcmcp_refresh_event_dates( $id );
					return array( 'id' => $id, 'recurrence' => fcmcp_recurrence_to_array( $id ) );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		/* ---- MEDIA: SEARCH + LABEL ---- */

		wp_register_ability(
			'firstchurch/search-media',
			array(
				'label'               => 'Search media',
				'description'         => 'Browse/search the media library (images by default). Returns id, title, alt-text label, caption, dimensions and URLs so an image can be reused by id. Use only_unlabeled=true to find images missing an alt-text label.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'         => array( 'type' => 'string', 'description' => 'Match filename, title, caption, description, and alt-text label.' ),
						'limit'          => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'mime_type'      => array( 'type' => 'string', 'description' => 'MIME filter, e.g. "image" (default) or "image/png".', 'default' => 'image' ),
						'only_unlabeled' => array( 'type' => 'boolean', 'description' => 'Only return images with no alt-text label (candidates for labeling).', 'default' => false ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_media',
				'permission_callback' => static function () { return current_user_can( 'upload_files' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/label-image',
			array(
				'label'               => 'Label image',
				'description'         => 'Describe an image to improve future selection. Writes a concise description to the image\'s alt text (used for accessibility and search), and optionally sets caption/title. Intended for agents to label unlabeled images using their vision — get each image URL from search-media.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'      => array( 'type' => 'integer' ),
						'label'   => array( 'type' => 'string', 'description' => 'Concise description of what the image shows (becomes the alt text).' ),
						'caption' => array( 'type' => 'string', 'description' => 'Optional caption shown under the image.' ),
						'title'   => array( 'type' => 'string', 'description' => 'Optional human-friendly title.' ),
					),
					'required'             => array( 'id', 'label' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_label_image',
				'permission_callback' => static function () { return current_user_can( 'upload_files' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		/* ---- DASHBOARD: REVIEW QUEUE ---- */

		wp_register_ability(
			'firstchurch/review-queue',
			array(
				'label'               => 'Review queue',
				'description'         => 'List all draft/pending events, announcements, and sermons awaiting human review and publishing — the publish queue for the draft-first workflow. Each item includes an edit URL. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'both' ), 'default' => 'both' ),
						'types'  => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'events', 'announcements', 'sermons' ) ), 'description' => 'Which content types to include (default: all three).' ),
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25, 'description' => 'Max items per type.' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_review_queue',
				'permission_callback' => static function () { return current_user_can( 'edit_posts' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		/* ---- SERMONS: READ ---- */

		wp_register_ability(
			'firstchurch/search-sermons',
			array(
				'label'               => 'Search sermons',
				'description'         => 'Search sermons (ctc_sermon), newest first. Filter by series/speaker/topic/book/tag slug, free text, and status. Drafts/pending require edit permission. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'search'  => array( 'type' => 'string' ),
						'series'  => array( 'type' => 'string', 'description' => 'ctc_sermon_series slug' ),
						'speaker' => array( 'type' => 'string', 'description' => 'ctc_sermon_speaker slug' ),
						'topic'   => array( 'type' => 'string', 'description' => 'ctc_sermon_topic slug' ),
						'book'    => array( 'type' => 'string', 'description' => 'ctc_sermon_book slug' ),
						'tag'     => array( 'type' => 'string', 'description' => 'ctc_sermon_tag slug' ),
						'status'  => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'any' ), 'default' => 'publish' ),
						'order'   => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'default' => 'desc' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_sermons',
				'permission_callback' => static function ( $input = array() ) {
					$status = $input['status'] ?? 'publish';
					if ( 'publish' !== $status && ! current_user_can( 'edit_posts' ) ) {
						return new WP_Error( 'forbidden', 'Viewing non-published sermons requires edit permission.' );
					}
					return current_user_can( 'read' );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-sermon',
			array(
				'label'               => 'Get sermon',
				'description'         => 'Get full detail for one sermon by ID, including video/audio/pdf and taxonomies. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'ctc_sermon' !== $post->post_type ) {
						return new WP_Error( 'not_found', 'Sermon not found.' );
					}
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						return new WP_Error( 'forbidden', 'Not permitted to view this sermon.' );
					}
					$data            = fcmcp_sermon_to_array( $post );
					$data['content'] = apply_filters( 'the_content', $post->post_content );
					return $data;
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/list-sermon-terms',
			array(
				'label'               => 'List sermon terms',
				'description'         => 'List the terms of a sermon taxonomy (series, speaker, topic, book, or tag) with slug, name, and count. Use to find valid filter/assignment values. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'taxonomy' => array( 'type' => 'string', 'enum' => array( 'series', 'speaker', 'topic', 'book', 'tag' ) ),
					),
					'required'             => array( 'taxonomy' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$map = array( 'series' => 'ctc_sermon_series', 'speaker' => 'ctc_sermon_speaker', 'topic' => 'ctc_sermon_topic', 'book' => 'ctc_sermon_book', 'tag' => 'ctc_sermon_tag' );
					$tax = $map[ $input['taxonomy'] ] ?? '';
					if ( ! $tax ) {
						return new WP_Error( 'bad_taxonomy', 'Unknown taxonomy.' );
					}
					$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
					$out   = array();
					foreach ( $terms as $t ) {
						$out[] = array( 'slug' => $t->slug, 'name' => $t->name, 'count' => (int) $t->count );
					}
					return array( 'taxonomy' => $input['taxonomy'], 'terms' => $out );
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		/* ---- SERMONS: WRITE ---- */

		wp_register_ability(
			'firstchurch/create-sermon',
			array(
				'label'               => 'Create sermon',
				'description'         => 'Create a sermon. Defaults to a DRAFT; set status=pending to queue for approval or status=publish to go live. The sermon date is the post date. Series/speaker/topic/book/tag accept names or slugs (created if missing).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string', 'description' => 'Sermon body/notes (may contain basic HTML).' ),
						'excerpt'     => array( 'type' => 'string' ),
						'date'        => array( 'type' => 'string', 'description' => 'Sermon date / post date as YYYY-MM-DD or YYYY-MM-DD HH:MM (defaults to today). Past backdates; future schedules it to auto-publish then.' ),
						'video'       => array( 'type' => 'string', 'description' => 'Video URL (e.g. YouTube).' ),
						'audio'       => array( 'type' => 'string', 'description' => 'Audio URL.' ),
						'pdf'         => array( 'type' => 'string', 'description' => 'PDF/notes URL.' ),
						'series'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'speakers'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'topics'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'books'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'image_id'    => array( 'type' => 'integer', 'description' => 'Existing attachment ID for featured image.' ),
						'image_url'   => array( 'type' => 'string', 'description' => 'Image URL to download and set as featured image.' ),
						'status'      => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft' ),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_sermon',
				'permission_callback' => $can_edit,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-sermon',
			array(
				'label'               => 'Update sermon',
				'description'         => 'Update fields of an existing sermon by ID. Does not change publish status (use set-sermon-status). Provided taxonomies replace existing ones for that taxonomy.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'          => array( 'type' => 'integer' ),
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'excerpt'     => array( 'type' => 'string' ),
						'date'        => array( 'type' => 'string', 'description' => 'Sermon date / post date as YYYY-MM-DD or YYYY-MM-DD HH:MM. Past backdates; future schedules it to auto-publish then.' ),
						'video'       => array( 'type' => 'string' ),
						'audio'       => array( 'type' => 'string' ),
						'pdf'         => array( 'type' => 'string' ),
						'series'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'speakers'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'topics'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'books'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'image_id'    => array( 'type' => 'integer' ),
						'image_url'   => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_sermon',
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-sermon-status',
			array(
				'label'               => 'Set sermon status',
				'description'         => 'Set a sermon to draft, pending (queue for approval), or publish (go live now).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
					),
					'required'             => array( 'id', 'status' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_set_status( (int) $input['id'], (string) $input['status'], 'ctc_sermon' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/trash-sermon',
			array(
				'label'               => 'Trash sermon',
				'description'         => 'Move a sermon to the Trash (recoverable).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_trash( (int) $input['id'], 'ctc_sermon' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'delete_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);

		/* ---- POSTS (general blog posts, any category) ---- */

		wp_register_ability(
			'firstchurch/search-posts',
			array(
				'label'               => 'Search posts',
				'description'         => 'Search blog posts (any category), newest first. Filter by category slug, free text, and status. Drafts/pending require edit permission. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'      => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'search'     => array( 'type' => 'string' ),
						'category'   => array( 'type' => 'string', 'description' => 'category slug' ),
						'since_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD lower bound' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'any' ), 'default' => 'publish' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_posts',
				'permission_callback' => static function ( $input = array() ) {
					$status = $input['status'] ?? 'publish';
					if ( 'publish' !== $status && ! current_user_can( 'edit_posts' ) ) {
						return new WP_Error( 'forbidden', 'Viewing non-published posts requires edit permission.' );
					}
					return current_user_can( 'read' );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-post',
			array(
				'label'               => 'Get post',
				'description'         => 'Get one blog post by ID, including full content. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'post' !== $post->post_type ) {
						return new WP_Error( 'not_found', 'Post not found.' );
					}
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						return new WP_Error( 'forbidden', 'Not permitted to view this post.' );
					}
					$data            = fcmcp_post_to_array( $post );
					$data['content'] = apply_filters( 'the_content', $post->post_content );
					$data['tags']    = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
					return $data;
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/list-post-categories',
			array(
				'label'               => 'List post categories',
				'description'         => 'List blog post categories (slug, name, count). Read-only.',
				'category'            => 'firstchurch',
				'execute_callback'    => static function () {
					$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
					$out   = array();
					foreach ( $terms as $t ) {
						$out[] = array( 'slug' => $t->slug, 'name' => $t->name, 'count' => (int) $t->count );
					}
					return array( 'categories' => $out );
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/create-post',
			array(
				'label'               => 'Create post',
				'description'         => 'Create a blog post. Defaults to a DRAFT; set status=pending (queue for approval) or status=publish (go live). Categories/tags accept names or slugs (created if missing).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string', 'description' => 'Body (may contain basic HTML).' ),
						'excerpt'    => array( 'type' => 'string' ),
						'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'image_id'   => array( 'type' => 'integer' ),
						'image_url'  => array( 'type' => 'string' ),
						'date'       => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then. Defaults to now.' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft' ),
					),
					'required'             => array( 'title', 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_post',
				'permission_callback' => $can_edit,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-post',
			array(
				'label'               => 'Update post',
				'description'         => 'Update an existing blog post by ID (title/content/excerpt/categories/tags/image). Does not change publish status (use set-post-status). Provided categories/tags replace existing.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'         => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'image_id'   => array( 'type' => 'integer' ),
						'image_url'  => array( 'type' => 'string' ),
						'date'       => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then.' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_post',
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-post-status',
			array(
				'label'               => 'Set post status',
				'description'         => 'Set a blog post to draft, pending (queue for approval), or publish (go live now).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
					),
					'required'             => array( 'id', 'status' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_set_status( (int) $input['id'], (string) $input['status'], 'post' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/trash-post',
			array(
				'label'               => 'Trash post',
				'description'         => 'Move a blog post to the Trash (recoverable).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_trash( (int) $input['id'], 'post' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'delete_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);

		/* ---- PAGES ---- */

		wp_register_ability(
			'firstchurch/search-pages',
			array(
				'label'               => 'Search pages',
				'description'         => 'Search pages by title/content, with optional parent filter. Drafts/pending require edit permission. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'search' => array( 'type' => 'string' ),
						'parent' => array( 'type' => 'integer', 'description' => 'Only children of this page ID.' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'any' ), 'default' => 'publish' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_pages',
				'permission_callback' => static function ( $input = array() ) {
					$status = $input['status'] ?? 'publish';
					if ( 'publish' !== $status && ! current_user_can( 'edit_pages' ) ) {
						return new WP_Error( 'forbidden', 'Viewing non-published pages requires edit permission.' );
					}
					return current_user_can( 'read' );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-page',
			array(
				'label'               => 'Get page',
				'description'         => 'Get one page by ID, including full content, parent, menu order and template. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'page' !== $post->post_type ) {
						return new WP_Error( 'not_found', 'Page not found.' );
					}
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						return new WP_Error( 'forbidden', 'Not permitted to view this page.' );
					}
					$data            = fcmcp_page_to_array( $post );
					$data['content'] = apply_filters( 'the_content', $post->post_content );
					return $data;
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/create-page',
			array(
				'label'               => 'Create page',
				'description'         => 'Create a page. Defaults to a DRAFT; set status=pending or status=publish. Supports parent (page ID), menu_order, and template (theme page-template file).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'parent'     => array( 'type' => 'integer', 'description' => 'Parent page ID.' ),
						'menu_order' => array( 'type' => 'integer' ),
						'template'   => array( 'type' => 'string', 'description' => 'Page template file, e.g. page-templates/foo.php.' ),
						'image_id'   => array( 'type' => 'integer' ),
						'image_url'  => array( 'type' => 'string' ),
						'date'       => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then. Defaults to now.' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft' ),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_page',
				'permission_callback' => static function () { return current_user_can( 'edit_pages' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-page',
			array(
				'label'               => 'Update page',
				'description'         => 'Update an existing page by ID (title/content/excerpt/parent/menu_order/template/image). Does not change publish status (use set-page-status).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'         => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'parent'     => array( 'type' => 'integer' ),
						'menu_order' => array( 'type' => 'integer' ),
						'template'   => array( 'type' => 'string' ),
						'image_id'   => array( 'type' => 'integer' ),
						'image_url'  => array( 'type' => 'string' ),
						'date'       => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then.' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_page',
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-page-status',
			array(
				'label'               => 'Set page status',
				'description'         => 'Set a page to draft, pending (queue for approval), or publish (go live now).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
					),
					'required'             => array( 'id', 'status' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_set_status( (int) $input['id'], (string) $input['status'], 'page' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/trash-page',
			array(
				'label'               => 'Trash page',
				'description'         => 'Move a page to the Trash (recoverable).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_trash( (int) $input['id'], 'page' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'delete_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);

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

		/* ---- NAVIGATION MENUS (wp_nav_menu) ---- */

		wp_register_ability(
			'firstchurch/list-menus',
			array(
				'label'               => 'List navigation menus',
				'description'         => 'List the site navigation menus (id, name, slug, item count, and which theme locations each is assigned to), plus the theme\'s registered menu locations. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_list_menus',
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-menu',
			array(
				'label'               => 'Get navigation menu',
				'description'         => 'Get one navigation menu by id, slug, or name, with its items in order. Each item carries id, title, url, type (post_type/taxonomy/custom), the linked object + id, parent item id, order, and target. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'menu' => array( 'type' => 'string', 'description' => 'Menu id, slug, or name. Use list-menus to find it.' ),
					),
					'required'             => array( 'menu' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_get_menu',
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/create-menu',
			array(
				'label'               => 'Create navigation menu',
				'description'         => 'Create a new (empty) navigation menu by name. Assigning it to a theme location is a separate theme decision and is not done here.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name' => array( 'type' => 'string' ),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_menu',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/add-menu-item',
			array(
				'label'               => 'Add navigation menu item',
				'description'         => 'Add an item to a navigation menu. Link target is exactly ONE of: page_id, post_id, category_id, or url (custom link — title required). Optional parent (an existing item id, for a submenu), position (1-based), target (_blank to open in a new tab), and description.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'menu'        => array( 'type' => 'string', 'description' => 'Menu id, slug, or name.' ),
						'title'       => array( 'type' => 'string', 'description' => 'Item label. Required for a custom-link (url) item; optional for object links (defaults to the linked item\'s title).' ),
						'page_id'     => array( 'type' => 'integer', 'description' => 'Link to a page by ID.' ),
						'post_id'     => array( 'type' => 'integer', 'description' => 'Link to a post by ID.' ),
						'category_id' => array( 'type' => 'integer', 'description' => 'Link to a category archive by term ID.' ),
						'url'         => array( 'type' => 'string', 'description' => 'Custom link URL (requires title).' ),
						'parent'      => array( 'type' => 'integer', 'description' => 'Parent menu item id (makes this a submenu entry).' ),
						'position'    => array( 'type' => 'integer', 'description' => '1-based position within the menu/level.' ),
						'target'      => array( 'type' => 'string', 'enum' => array( '', '_blank' ), 'description' => '_blank opens in a new tab.' ),
						'description' => array( 'type' => 'string' ),
						'attr_title'  => array( 'type' => 'string', 'description' => 'HTML title attribute (tooltip).' ),
					),
					'required'             => array( 'menu' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_add_menu_item',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-menu-item',
			array(
				'label'               => 'Update navigation menu item',
				'description'         => 'Update an existing menu item by id. Changeable: title, parent, position, target, description, attr_title — and url for a custom-link item. The link target type of an object link (page/post/category) is preserved; to relink, remove and re-add.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'          => array( 'type' => 'integer', 'description' => 'Menu item id (from get-menu).' ),
						'title'       => array( 'type' => 'string' ),
						'url'         => array( 'type' => 'string', 'description' => 'Custom-link items only.' ),
						'parent'      => array( 'type' => 'integer' ),
						'position'    => array( 'type' => 'integer' ),
						'target'      => array( 'type' => 'string', 'enum' => array( '', '_blank' ) ),
						'description' => array( 'type' => 'string' ),
						'attr_title'  => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_menu_item',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/remove-menu-item',
			array(
				'label'               => 'Remove navigation menu item',
				'description'         => 'Delete a menu item by id. Removes the item from the menu (does not delete the page/post it links to). Child items are re-parented by WordPress.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_remove_menu_item',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/reorder-menu',
			array(
				'label'               => 'Reorder navigation menu',
				'description'         => 'Set the top-to-bottom order of a menu by passing item ids in the desired order. Items not listed keep their relative order after the listed ones. Use update-menu-item to change an item\'s parent.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'menu'     => array( 'type' => 'string', 'description' => 'Menu id, slug, or name.' ),
						'item_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Menu item ids in the desired order.' ),
					),
					'required'             => array( 'menu', 'item_ids' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_reorder_menu',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);
	}
);

/* ----------------------------------------------------------------------------
 * Execute callbacks
 * ------------------------------------------------------------------------- */

/**
 * Build the WP_Query args for an event search. Split out from fcmcp_search_events
 * so the arg-construction (date-range meta_query, status mapping, tax/search
 * filters, ordering) is unit-testable without a live WP_Query/database.
 */
function fcmcp_build_event_query_args( array $input ): array {
	$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$from   = fcmcp_sanitize_date( $input['from_date'] ?? '' ) ?: current_time( 'Y-m-d' );
	$to     = fcmcp_sanitize_date( $input['to_date'] ?? '' );
	$order  = ( isset( $input['order'] ) && 'desc' === strtolower( $input['order'] ) ) ? 'DESC' : 'ASC';
	$status = $input['status'] ?? 'publish';

	$meta = array(
		array( 'key' => '_ctc_event_start_date', 'value' => $from, 'compare' => '>=', 'type' => 'DATE' ),
	);
	if ( $to ) {
		$meta[] = array( 'key' => '_ctc_event_start_date', 'value' => $to, 'compare' => '<=', 'type' => 'DATE' );
	}
	$meta['start'] = array( 'key' => '_ctc_event_start_date' );

	$args = array(
		'post_type'      => 'ctc_event',
		'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future' ) : $status,
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'meta_query'     => $meta,
		'orderby'        => array( 'start' => $order ),
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}
	if ( ! empty( $input['category'] ) ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'ctc_event_category', 'field' => 'slug', 'terms' => sanitize_title( $input['category'] ) ) );
	}
	return $args;
}

function fcmcp_search_events( $input = array() ) {
	$q   = new WP_Query( fcmcp_build_event_query_args( (array) $input ) );
	$out = array_map( 'fcmcp_event_to_array', $q->posts );
	return array( 'count' => count( $out ), 'events' => $out );
}

function fcmcp_apply_event_fields( int $post_id, array $input ): void {
	$map = array(
		'start_date'       => fn( $v ) => fcmcp_sanitize_date( $v ),
		'end_date'         => fn( $v ) => fcmcp_sanitize_date( $v ),
		'start_time'       => fn( $v ) => fcmcp_sanitize_time( $v ),
		'end_time'         => fn( $v ) => fcmcp_sanitize_time( $v ),
		'time_text'        => fn( $v ) => sanitize_text_field( $v ),
		'venue'            => fn( $v ) => sanitize_text_field( $v ),
		'address'          => fn( $v ) => sanitize_textarea_field( $v ),
		'registration_url' => fn( $v ) => esc_url_raw( $v ),
	);
	$keys = array(
		'start_date'       => '_ctc_event_start_date',
		'end_date'         => '_ctc_event_end_date',
		'start_time'       => '_ctc_event_start_time',
		'end_time'         => '_ctc_event_end_time',
		'time_text'        => '_ctc_event_time',
		'venue'            => '_ctc_event_venue',
		'address'          => '_ctc_event_address',
		'registration_url' => '_ctc_event_registration_url',
	);
	foreach ( $keys as $field => $meta_key ) {
		if ( array_key_exists( $field, $input ) ) {
			update_post_meta( $post_id, $meta_key, $map[ $field ]( $input[ $field ] ) );
		}
	}
	if ( ! empty( $input['category'] ) ) {
		wp_set_object_terms( $post_id, sanitize_title( $input['category'] ), 'ctc_event_category', false );
	}
	fcmcp_refresh_event_dates( $post_id );
}

function fcmcp_create_event( $input ) {
	$start = fcmcp_sanitize_date( $input['start_date'] ?? '' );
	if ( '' === $start ) {
		return new WP_Error( 'invalid_date', 'start_date must be YYYY-MM-DD.' );
	}
	$end = fcmcp_sanitize_date( $input['end_date'] ?? '' ) ?: $start;
	if ( $end < $start ) {
		return new WP_Error( 'invalid_range', 'end_date cannot be before start_date.' );
	}

	$post_id = wp_insert_post(
		fcmcp_apply_post_date(
			array(
				'post_type'    => 'ctc_event',
				'post_status'  => fcmcp_new_status( $input ),
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_content' => wp_kses_post( $input['description'] ?? '' ),
			),
			$input
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	$input['end_date'] = $end;
	fcmcp_apply_event_fields( (int) $post_id, $input );

	if ( isset( $input['recurrence'] ) ) {
		fcmcp_apply_recurrence( (int) $post_id, $input['recurrence'] );
		fcmcp_refresh_event_dates( (int) $post_id );
	} else {
		update_post_meta( $post_id, '_ctc_event_recurrence', 'none' );
	}

	$result = array( 'id' => (int) $post_id, 'status' => get_post_status( $post_id ), 'edit_url' => get_edit_post_link( $post_id, 'raw' ) );
	$img    = fcmcp_set_featured_image( (int) $post_id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['event'] = fcmcp_event_to_array( get_post( $post_id ) );
	return $result;
}

function fcmcp_update_event( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'ctc_event' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Event not found.' );
	}
	$core = array( 'ID' => $id );
	if ( array_key_exists( 'title', $input ) ) {
		$core['post_title'] = sanitize_text_field( $input['title'] );
	}
	if ( array_key_exists( 'description', $input ) ) {
		$core['post_content'] = wp_kses_post( $input['description'] );
	}
	$core = fcmcp_apply_post_date( $core, $input );
	if ( count( $core ) > 1 ) {
		$r = wp_update_post( $core, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	fcmcp_apply_event_fields( $id, $input );

	if ( isset( $input['recurrence'] ) ) {
		fcmcp_apply_recurrence( $id, $input['recurrence'] );
		fcmcp_refresh_event_dates( $id );
	}

	$result = array( 'id' => $id, 'status' => get_post_status( $id ) );
	$img    = fcmcp_set_featured_image( $id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['event'] = fcmcp_event_to_array( get_post( $id ) );
	return $result;
}

/**
 * Build the WP_Query args for an announcements listing (Announcements category,
 * status mapping, free-text, since-date lower bound). Split out for testability.
 */
function fcmcp_build_announcement_query_args( array $input ): array {
	$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$status = $input['status'] ?? 'publish';
	$args   = array(
		'post_type'      => 'post',
		'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future' ) : $status,
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'cat'            => fcmcp_announce_cat_id(),
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}
	if ( ! empty( $input['since_date'] ) && fcmcp_sanitize_date( $input['since_date'] ) ) {
		$args['date_query'] = array( array( 'after' => $input['since_date'], 'inclusive' => true ) );
	}
	return $args;
}

function fcmcp_list_announcements( $input = array() ) {
	$q   = new WP_Query( fcmcp_build_announcement_query_args( (array) $input ) );
	$out = array_map( static function ( $p ) { return array_merge( fcmcp_post_to_array( $p ), fcmcp_announcement_extra( $p ) ); }, $q->posts );
	return array( 'count' => count( $out ), 'announcements' => $out );
}

/** Write the announcement call-to-action button meta (fcs_cta_text/fcs_cta_url). */
function fcmcp_apply_cta( int $post_id, array $input ): void {
	if ( array_key_exists( 'cta_text', $input ) ) {
		update_post_meta( $post_id, 'fcs_cta_text', sanitize_text_field( (string) $input['cta_text'] ) );
	}
	if ( array_key_exists( 'cta_url', $input ) ) {
		update_post_meta( $post_id, 'fcs_cta_url', esc_url_raw( (string) $input['cta_url'] ) );
	}
}

/** Write announcement lifecycle meta: fcs_weight (prominence) + fcs_expires (date). See ops/docs/happenings.md. */
function fcmcp_apply_announcement_lifecycle( int $post_id, array $input ): void {
	if ( array_key_exists( 'weight', $input ) ) {
		update_post_meta( $post_id, 'fcs_weight', absint( $input['weight'] ) );
	}
	if ( array_key_exists( 'expires', $input ) ) {
		update_post_meta( $post_id, 'fcs_expires', fcmcp_sanitize_date( (string) $input['expires'] ) );
	}
}

/** Lifecycle fields echoed back on announcement reads (kept off the shared post serializer so posts/pages don't carry them). */
function fcmcp_announcement_extra( WP_Post $post ): array {
	return array(
		'weight'  => (int) get_post_meta( $post->ID, 'fcs_weight', true ),
		'expires' => (string) get_post_meta( $post->ID, 'fcs_expires', true ),
	);
}

function fcmcp_create_announcement( $input ) {
	$cat = fcmcp_announce_cat_id();
	if ( ! $cat ) {
		return new WP_Error( 'no_category', 'Announcements category not found.' );
	}
	$post_id = wp_insert_post(
		fcmcp_apply_post_date(
			array(
				'post_type'     => 'post',
				'post_status'   => fcmcp_new_status( $input ),
				'post_title'    => sanitize_text_field( $input['title'] ),
				'post_content'  => wp_kses_post( $input['content'] ),
				'post_excerpt'  => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
				'post_category' => array( $cat ),
			),
			$input
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	fcmcp_apply_cta( (int) $post_id, $input );
	fcmcp_apply_announcement_lifecycle( (int) $post_id, $input );
	$result = array( 'id' => (int) $post_id, 'status' => get_post_status( $post_id ), 'edit_url' => get_edit_post_link( $post_id, 'raw' ) );
	$img    = fcmcp_set_featured_image( (int) $post_id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['announcement'] = array_merge( fcmcp_post_to_array( get_post( $post_id ) ), fcmcp_announcement_extra( get_post( $post_id ) ) );
	return $result;
}

function fcmcp_update_announcement( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'post' !== $post->post_type || ! has_category( fcmcp_announce_cat_id(), $post ) ) {
		return new WP_Error( 'not_found', 'Announcement not found.' );
	}
	$core = array( 'ID' => $id );
	if ( array_key_exists( 'title', $input ) ) {
		$core['post_title'] = sanitize_text_field( $input['title'] );
	}
	if ( array_key_exists( 'content', $input ) ) {
		$core['post_content'] = wp_kses_post( $input['content'] );
	}
	if ( array_key_exists( 'excerpt', $input ) ) {
		$core['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
	}
	$core = fcmcp_apply_post_date( $core, $input );
	if ( count( $core ) > 1 ) {
		$r = wp_update_post( $core, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	fcmcp_apply_cta( $id, $input );
	fcmcp_apply_announcement_lifecycle( $id, $input );
	$result = array( 'id' => $id, 'status' => get_post_status( $id ) );
	$img    = fcmcp_set_featured_image( $id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['announcement'] = array_merge( fcmcp_post_to_array( get_post( $id ) ), fcmcp_announcement_extra( get_post( $id ) ) );
	return $result;
}

/** Validate a requested status for create/update (draft default, pending = queue for approval, publish = go live). */
function fcmcp_new_status( $input ): string {
	$s = isset( $input['status'] ) ? strtolower( (string) $input['status'] ) : 'draft';
	return in_array( $s, array( 'draft', 'pending', 'publish' ), true ) ? $s : 'draft';
}

function fcmcp_set_status( int $id, string $status, string $expected_type ) {
	if ( ! in_array( $status, array( 'draft', 'pending', 'publish' ), true ) ) {
		return new WP_Error( 'bad_status', 'status must be draft, pending, or publish.' );
	}
	$post = get_post( $id );
	if ( ! $post || $expected_type !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Item not found.' );
	}
	$r = wp_update_post( array( 'ID' => $id, 'post_status' => $status ), true );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return array( 'id' => $id, 'status' => $status );
}

function fcmcp_trash( int $id, string $expected_type ) {
	$post = get_post( $id );
	if ( ! $post || $expected_type !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Item not found.' );
	}
	$r = wp_trash_post( $id );
	if ( ! $r ) {
		return new WP_Error( 'trash_failed', 'Could not trash the item.' );
	}
	return array( 'id' => $id, 'status' => 'trash' );
}

function fcmcp_media_to_array( WP_Post $post ): array {
	$alt  = (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
	$meta = wp_get_attachment_metadata( $post->ID );
	return array(
		'id'            => $post->ID,
		'title'         => get_the_title( $post ),
		'alt'           => $alt,
		'caption'       => $post->post_excerpt,
		'description'   => $post->post_content,
		'mime_type'     => $post->post_mime_type,
		'labeled'       => ( '' !== $alt ),
		'width'         => isset( $meta['width'] ) ? (int) $meta['width'] : null,
		'height'        => isset( $meta['height'] ) ? (int) $meta['height'] : null,
		'url'           => (string) wp_get_attachment_url( $post->ID ),
		'thumbnail_url' => (string) wp_get_attachment_image_url( $post->ID, 'thumbnail' ),
	);
}

function fcmcp_search_media( $input = array() ) {
	$limit     = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$term      = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';
	$mime      = ! empty( $input['mime_type'] ) ? sanitize_text_field( $input['mime_type'] ) : 'image';
	$unlabeled = ! empty( $input['only_unlabeled'] );

	$base = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'post_mime_type' => $mime,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( $unlabeled ) {
		$base['meta_query'] = array(
			'relation' => 'OR',
			array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ),
		);
	}

	$found = array();
	if ( '' !== $term && ! $unlabeled ) {
		// Match title/caption/description/filename, then also alt-text label; merge unique.
		$qa = new WP_Query( array_merge( $base, array( 's' => $term ) ) );
		foreach ( $qa->posts as $p ) {
			$found[ $p->ID ] = $p;
		}
		$qb = new WP_Query( array_merge( $base, array( 'meta_query' => array( array( 'key' => '_wp_attachment_image_alt', 'value' => $term, 'compare' => 'LIKE' ) ) ) ) );
		foreach ( $qb->posts as $p ) {
			$found[ $p->ID ] = $p;
		}
	} else {
		if ( '' !== $term ) {
			$base['s'] = $term;
		}
		$q = new WP_Query( $base );
		foreach ( $q->posts as $p ) {
			$found[ $p->ID ] = $p;
		}
	}

	$found = array_slice( $found, 0, $limit, true );
	$out   = array_map( 'fcmcp_media_to_array', array_values( $found ) );
	return array( 'count' => count( $out ), 'media' => $out );
}

function fcmcp_label_image( $input ) {
	$id  = (int) ( $input['id'] ?? 0 );
	$att = get_post( $id );
	if ( ! $att || 'attachment' !== $att->post_type ) {
		return new WP_Error( 'not_found', 'Attachment not found.' );
	}
	if ( 0 !== strpos( (string) $att->post_mime_type, 'image/' ) ) {
		return new WP_Error( 'not_image', 'Only image attachments can be labeled.' );
	}
	if ( isset( $input['label'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $input['label'] ) );
	}
	$core = array( 'ID' => $id );
	if ( array_key_exists( 'title', $input ) ) {
		$core['post_title'] = sanitize_text_field( $input['title'] );
	}
	if ( array_key_exists( 'caption', $input ) ) {
		$core['post_excerpt'] = sanitize_text_field( $input['caption'] );
	}
	if ( count( $core ) > 1 ) {
		$r = wp_update_post( $core, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	return fcmcp_media_to_array( get_post( $id ) );
}

function fcmcp_review_queue( $input = array() ) {
	$status_in = $input['status'] ?? 'both';
	$statuses  = 'both' === $status_in ? array( 'draft', 'pending' ) : array_values( array_intersect( array( $status_in ), array( 'draft', 'pending' ) ) );
	if ( ! $statuses ) {
		$statuses = array( 'draft', 'pending' );
	}
	$limit = max( 1, min( 100, (int) ( $input['limit'] ?? 25 ) ) );
	$types = ( ! empty( $input['types'] ) && is_array( $input['types'] ) ) ? $input['types'] : array( 'events', 'announcements', 'sermons' );

	$sources = array(
		'events'        => array( 'post_type' => 'ctc_event', 'label' => 'event' ),
		'announcements' => array( 'post_type' => 'post', 'label' => 'announcement', 'cat' => fcmcp_announce_cat_id() ),
		'sermons'       => array( 'post_type' => 'ctc_sermon', 'label' => 'sermon' ),
	);

	$items  = array();
	$counts = array();
	foreach ( $sources as $key => $src ) {
		if ( ! in_array( $key, $types, true ) ) {
			continue;
		}
		$args = array(
			'post_type'      => $src['post_type'],
			'post_status'    => $statuses,
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( isset( $src['cat'] ) ) {
			$args['cat'] = $src['cat'];
		}
		$q = new WP_Query( $args );
		foreach ( $q->posts as $p ) {
			$item = array(
				'type'     => $src['label'],
				'id'       => $p->ID,
				'title'    => get_the_title( $p ),
				'status'   => $p->post_status,
				'author'   => get_the_author_meta( 'display_name', $p->post_author ),
				'modified' => get_post_modified_time( 'Y-m-d H:i', false, $p ),
				'edit_url' => admin_url( 'post.php?post=' . $p->ID . '&action=edit' ),
			);
			if ( 'ctc_event' === $src['post_type'] ) {
				$item['start_date'] = (string) get_post_meta( $p->ID, '_ctc_event_start_date', true );
			}
			$items[] = $item;
		}
		$counts[ $key ] = count( $q->posts );
	}
	$counts['total'] = count( $items );

	return array( 'counts' => $counts, 'items' => $items );
}

/* ----------------------------------------------------------------------------
 * Sermons
 * ------------------------------------------------------------------------- */

function fcmcp_sermon_to_array( WP_Post $post ): array {
	return array(
		'id'             => $post->ID,
		'title'          => get_the_title( $post ),
		'status'         => $post->post_status,
		'date'           => get_post_time( 'Y-m-d', false, $post ),
		'video'          => (string) get_post_meta( $post->ID, '_ctc_sermon_video', true ),
		'audio'          => (string) get_post_meta( $post->ID, '_ctc_sermon_audio', true ),
		'pdf'            => (string) get_post_meta( $post->ID, '_ctc_sermon_pdf', true ),
		'series'         => wp_get_post_terms( $post->ID, 'ctc_sermon_series', array( 'fields' => 'names' ) ),
		'speakers'       => wp_get_post_terms( $post->ID, 'ctc_sermon_speaker', array( 'fields' => 'names' ) ),
		'topics'         => wp_get_post_terms( $post->ID, 'ctc_sermon_topic', array( 'fields' => 'names' ) ),
		'books'          => wp_get_post_terms( $post->ID, 'ctc_sermon_book', array( 'fields' => 'names' ) ),
		'tags'           => wp_get_post_terms( $post->ID, 'ctc_sermon_tag', array( 'fields' => 'names' ) ),
		'excerpt'        => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
		'featured_image' => (string) get_the_post_thumbnail_url( $post, 'full' ),
		'url'            => (string) get_permalink( $post ),
		'edit_url'       => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
	);
}

/** Resolve a list of names/slugs to term IDs in a taxonomy, creating missing terms. */
function fcmcp_resolve_terms( string $taxonomy, $values ): array {
	$ids = array();
	foreach ( (array) $values as $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			continue;
		}
		$term = get_term_by( 'slug', sanitize_title( $v ), $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'name', $v, $taxonomy );
		}
		if ( $term ) {
			$ids[] = (int) $term->term_id;
		} else {
			$new = wp_insert_term( $v, $taxonomy );
			if ( ! is_wp_error( $new ) ) {
				$ids[] = (int) $new['term_id'];
			}
		}
	}
	return $ids;
}

function fcmcp_apply_sermon_fields( int $post_id, array $input ): void {
	$meta = array( 'video' => '_ctc_sermon_video', 'audio' => '_ctc_sermon_audio', 'pdf' => '_ctc_sermon_pdf' );
	foreach ( $meta as $field => $key ) {
		if ( array_key_exists( $field, $input ) ) {
			update_post_meta( $post_id, $key, esc_url_raw( (string) $input[ $field ] ) );
		}
	}
	$tax = array(
		'series'   => 'ctc_sermon_series',
		'speakers' => 'ctc_sermon_speaker',
		'topics'   => 'ctc_sermon_topic',
		'books'    => 'ctc_sermon_book',
		'tags'     => 'ctc_sermon_tag',
	);
	foreach ( $tax as $field => $taxonomy ) {
		if ( array_key_exists( $field, $input ) ) {
			wp_set_object_terms( $post_id, fcmcp_resolve_terms( $taxonomy, $input[ $field ] ), $taxonomy, false );
		}
	}
}

/**
 * Build the WP_Query args for a sermon search (status mapping, free-text, and
 * the AND-combined taxonomy filters). Split out for unit-testability.
 */
function fcmcp_build_sermon_query_args( array $input ): array {
	$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$status = $input['status'] ?? 'publish';
	$order  = ( isset( $input['order'] ) && 'asc' === strtolower( $input['order'] ) ) ? 'ASC' : 'DESC';

	$args = array(
		'post_type'      => 'ctc_sermon',
		'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future' ) : $status,
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => $order,
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}
	$tax_map   = array( 'series' => 'ctc_sermon_series', 'speaker' => 'ctc_sermon_speaker', 'topic' => 'ctc_sermon_topic', 'book' => 'ctc_sermon_book', 'tag' => 'ctc_sermon_tag' );
	$tax_query = array();
	foreach ( $tax_map as $field => $taxonomy ) {
		if ( ! empty( $input[ $field ] ) ) {
			$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => sanitize_title( $input[ $field ] ) );
		}
	}
	if ( $tax_query ) {
		$tax_query['relation'] = 'AND';
		$args['tax_query']     = $tax_query;
	}
	return $args;
}

function fcmcp_search_sermons( $input = array() ) {
	$q   = new WP_Query( fcmcp_build_sermon_query_args( (array) $input ) );
	$out = array_map( 'fcmcp_sermon_to_array', $q->posts );
	return array( 'count' => count( $out ), 'sermons' => $out );
}

function fcmcp_create_sermon( $input ) {
	$postarr = array(
		'post_type'    => 'ctc_sermon',
		'post_status'  => fcmcp_new_status( $input ),
		'post_title'   => sanitize_text_field( $input['title'] ),
		'post_content' => wp_kses_post( $input['description'] ?? '' ),
		'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
	);
	$postarr = fcmcp_apply_post_date( $postarr, $input );
	$post_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	fcmcp_apply_sermon_fields( (int) $post_id, $input );

	$result = array( 'id' => (int) $post_id, 'status' => get_post_status( $post_id ), 'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
	$img    = fcmcp_set_featured_image( (int) $post_id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['sermon'] = fcmcp_sermon_to_array( get_post( $post_id ) );
	return $result;
}

function fcmcp_update_sermon( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'ctc_sermon' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Sermon not found.' );
	}
	$core = array( 'ID' => $id );
	if ( array_key_exists( 'title', $input ) ) {
		$core['post_title'] = sanitize_text_field( $input['title'] );
	}
	if ( array_key_exists( 'description', $input ) ) {
		$core['post_content'] = wp_kses_post( $input['description'] );
	}
	if ( array_key_exists( 'excerpt', $input ) ) {
		$core['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
	}
	$core = fcmcp_apply_post_date( $core, $input );
	if ( count( $core ) > 1 ) {
		$r = wp_update_post( $core, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	fcmcp_apply_sermon_fields( $id, $input );

	$result = array( 'id' => $id, 'status' => get_post_status( $id ) );
	$img    = fcmcp_set_featured_image( $id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['sermon'] = fcmcp_sermon_to_array( get_post( $id ) );
	return $result;
}

/* ----------------------------------------------------------------------------
 * Posts (general) + Pages
 * ------------------------------------------------------------------------- */

/**
 * Build the WP_Query args for a blog-post search (status mapping, free-text,
 * category slug, since-date lower bound). Split out for unit-testability.
 */
function fcmcp_build_post_query_args( array $input ): array {
	$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$status = $input['status'] ?? 'publish';
	$args   = array(
		'post_type'      => 'post',
		'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future' ) : $status,
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}
	if ( ! empty( $input['category'] ) ) {
		$args['category_name'] = sanitize_title( $input['category'] );
	}
	if ( ! empty( $input['since_date'] ) && fcmcp_sanitize_date( $input['since_date'] ) ) {
		$args['date_query'] = array( array( 'after' => $input['since_date'], 'inclusive' => true ) );
	}
	return $args;
}

function fcmcp_search_posts( $input = array() ) {
	$q   = new WP_Query( fcmcp_build_post_query_args( (array) $input ) );
	$out = array_map( 'fcmcp_post_to_array', $q->posts );
	return array( 'count' => count( $out ), 'posts' => $out );
}

function fcmcp_create_post( $input ) {
	$post_id = wp_insert_post( fcmcp_apply_post_date( array(
		'post_type'    => 'post',
		'post_status'  => fcmcp_new_status( $input ),
		'post_title'   => sanitize_text_field( $input['title'] ),
		'post_content' => wp_kses_post( $input['content'] ?? '' ),
		'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
	), $input ), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	if ( array_key_exists( 'categories', $input ) ) {
		wp_set_object_terms( $post_id, fcmcp_resolve_terms( 'category', $input['categories'] ), 'category', false );
	}
	if ( array_key_exists( 'tags', $input ) ) {
		wp_set_object_terms( $post_id, fcmcp_resolve_terms( 'post_tag', $input['tags'] ), 'post_tag', false );
	}
	$result = array( 'id' => (int) $post_id, 'status' => get_post_status( $post_id ), 'edit_url' => get_edit_post_link( $post_id, 'raw' ) );
	$img    = fcmcp_set_featured_image( (int) $post_id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['post'] = fcmcp_post_to_array( get_post( $post_id ) );
	return $result;
}

function fcmcp_update_post( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Post not found.' );
	}
	$core = array( 'ID' => $id );
	if ( array_key_exists( 'title', $input ) ) {
		$core['post_title'] = sanitize_text_field( $input['title'] );
	}
	if ( array_key_exists( 'content', $input ) ) {
		$core['post_content'] = wp_kses_post( $input['content'] );
	}
	if ( array_key_exists( 'excerpt', $input ) ) {
		$core['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
	}
	$core = fcmcp_apply_post_date( $core, $input );
	if ( count( $core ) > 1 ) {
		$r = wp_update_post( $core, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	if ( array_key_exists( 'categories', $input ) ) {
		wp_set_object_terms( $id, fcmcp_resolve_terms( 'category', $input['categories'] ), 'category', false );
	}
	if ( array_key_exists( 'tags', $input ) ) {
		wp_set_object_terms( $id, fcmcp_resolve_terms( 'post_tag', $input['tags'] ), 'post_tag', false );
	}
	$result = array( 'id' => $id, 'status' => get_post_status( $id ) );
	$img    = fcmcp_set_featured_image( $id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['post'] = fcmcp_post_to_array( get_post( $id ) );
	return $result;
}

function fcmcp_page_to_array( WP_Post $post ): array {
	$tpl = (string) get_post_meta( $post->ID, '_wp_page_template', true );
	return array(
		'id'             => $post->ID,
		'title'          => get_the_title( $post ),
		'status'         => $post->post_status,
		'parent'         => (int) $post->post_parent,
		'menu_order'     => (int) $post->menu_order,
		'template'       => $tpl ?: 'default',
		'excerpt'        => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
		'featured_image' => (string) get_the_post_thumbnail_url( $post, 'full' ),
		'url'            => (string) get_permalink( $post ),
		'edit_url'       => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
	);
}

/**
 * Build the WP_Query args for a page search (status mapping, free-text, parent
 * filter, menu-order/title ordering). Split out for unit-testability.
 */
function fcmcp_build_page_query_args( array $input ): array {
	$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$status = $input['status'] ?? 'publish';
	$args   = array(
		'post_type'      => 'page',
		'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future' ) : $status,
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}
	if ( isset( $input['parent'] ) ) {
		$args['post_parent'] = (int) $input['parent'];
	}
	return $args;
}

function fcmcp_search_pages( $input = array() ) {
	$q   = new WP_Query( fcmcp_build_page_query_args( (array) $input ) );
	$out = array_map( 'fcmcp_page_to_array', $q->posts );
	return array( 'count' => count( $out ), 'pages' => $out );
}

function fcmcp_create_page( $input ) {
	$arr = array(
		'post_type'    => 'page',
		'post_status'  => fcmcp_new_status( $input ),
		'post_title'   => sanitize_text_field( $input['title'] ),
		'post_content' => wp_kses_post( $input['content'] ?? '' ),
		'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
	);
	if ( isset( $input['parent'] ) ) {
		$arr['post_parent'] = (int) $input['parent'];
	}
	if ( isset( $input['menu_order'] ) ) {
		$arr['menu_order'] = (int) $input['menu_order'];
	}
	if ( ! empty( $input['template'] ) ) {
		$arr['page_template'] = sanitize_text_field( $input['template'] );
	}
	$arr     = fcmcp_apply_post_date( $arr, $input );
	$post_id = wp_insert_post( $arr, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	$result = array( 'id' => (int) $post_id, 'status' => get_post_status( $post_id ), 'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
	$img    = fcmcp_set_featured_image( (int) $post_id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['page'] = fcmcp_page_to_array( get_post( $post_id ) );
	return $result;
}

function fcmcp_update_page( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Page not found.' );
	}
	$core = array( 'ID' => $id );
	if ( array_key_exists( 'title', $input ) ) {
		$core['post_title'] = sanitize_text_field( $input['title'] );
	}
	if ( array_key_exists( 'content', $input ) ) {
		$core['post_content'] = wp_kses_post( $input['content'] );
	}
	if ( array_key_exists( 'excerpt', $input ) ) {
		$core['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
	}
	if ( array_key_exists( 'parent', $input ) ) {
		$core['post_parent'] = (int) $input['parent'];
	}
	if ( array_key_exists( 'menu_order', $input ) ) {
		$core['menu_order'] = (int) $input['menu_order'];
	}
	$core = fcmcp_apply_post_date( $core, $input );
	if ( count( $core ) > 1 ) {
		$r = wp_update_post( $core, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	if ( array_key_exists( 'template', $input ) ) {
		update_post_meta( $id, '_wp_page_template', sanitize_text_field( $input['template'] ) );
	}
	$result = array( 'id' => $id, 'status' => get_post_status( $id ) );
	$img    = fcmcp_set_featured_image( $id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['page'] = fcmcp_page_to_array( get_post( $id ) );
	return $result;
}

/* ----------------------------------------------------------------------------
 * Redirects (Redirection plugin) — built on the Red_Item model API, not raw SQL,
 * so action_data serialization, source flags, group-cache flushing, and position
 * are all handled by the plugin. (The /enews/latest cron in bin/ writes raw SQL
 * on purpose; that is a separate, narrow exception.)
 * ------------------------------------------------------------------------- */

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

/* ----------------------------------------------------------------------------
 * Navigation menus (wp_nav_menu). Menu items are nav_menu_item posts in the
 * nav_menu taxonomy; we drive them through the core menu API (wp_update_nav_menu_item
 * etc.), gated by the narrow fcmcp_manage_menus cap so the app-password credential
 * never gets full edit_theme_options.
 * ------------------------------------------------------------------------- */

/** Flatten a (set-up) nav menu item object into a compact, stable shape. */
function fcmcp_menu_item_to_array( $item ): array {
	return array(
		'id'        => (int) $item->ID,
		'title'     => (string) $item->title,
		'url'       => (string) $item->url,
		'type'      => (string) $item->type,        // post_type | taxonomy | custom | post_type_archive
		'object'    => (string) $item->object,      // page | post | category | …
		'object_id' => (int) $item->object_id,
		'parent'    => (int) $item->menu_item_parent,
		'order'     => (int) $item->menu_order,
		'target'    => (string) $item->target,
	);
}

/** Map the type-agnostic fields shared by add/update onto a menu-item-* data array. */
function fcmcp_apply_menu_item_fields( array $data, array $input ): array {
	if ( array_key_exists( 'title', $input ) ) {
		$data['menu-item-title'] = sanitize_text_field( (string) $input['title'] );
	}
	if ( array_key_exists( 'parent', $input ) ) {
		$data['menu-item-parent-id'] = absint( $input['parent'] );
	}
	if ( array_key_exists( 'position', $input ) ) {
		$data['menu-item-position'] = absint( $input['position'] );
	}
	if ( array_key_exists( 'target', $input ) ) {
		$data['menu-item-target'] = ( '_blank' === $input['target'] ) ? '_blank' : '';
	}
	if ( array_key_exists( 'description', $input ) ) {
		$data['menu-item-description'] = sanitize_text_field( (string) $input['description'] );
	}
	if ( array_key_exists( 'attr_title', $input ) ) {
		$data['menu-item-attr-title'] = sanitize_text_field( (string) $input['attr_title'] );
	}
	return $data;
}

/**
 * Build the wp_update_nav_menu_item data array for a NEW item from MCP input.
 * Validates that exactly one link target is given (page_id|post_id|category_id|url)
 * and that a custom-link item carries a title. Returns the data array or WP_Error.
 */
function fcmcp_build_menu_item_args( array $input ) {
	$provided = array();
	foreach ( array( 'page_id', 'post_id', 'category_id', 'url' ) as $k ) {
		if ( isset( $input[ $k ] ) && '' !== $input[ $k ] && null !== $input[ $k ] ) {
			$provided[] = $k;
		}
	}
	if ( 1 !== count( $provided ) ) {
		return new WP_Error( 'bad_link_target', 'Provide exactly one of page_id, post_id, category_id, or url.' );
	}

	$data = array( 'menu-item-status' => 'publish' );
	switch ( $provided[0] ) {
		case 'page_id':
			$data['menu-item-type']      = 'post_type';
			$data['menu-item-object']    = 'page';
			$data['menu-item-object-id'] = absint( $input['page_id'] );
			break;
		case 'post_id':
			$data['menu-item-type']      = 'post_type';
			$data['menu-item-object']    = 'post';
			$data['menu-item-object-id'] = absint( $input['post_id'] );
			break;
		case 'category_id':
			$data['menu-item-type']      = 'taxonomy';
			$data['menu-item-object']    = 'category';
			$data['menu-item-object-id'] = absint( $input['category_id'] );
			break;
		case 'url':
			$url = esc_url_raw( (string) $input['url'] );
			if ( '' === $url ) {
				return new WP_Error( 'bad_url', 'url must be a valid URL.' );
			}
			if ( '' === trim( (string) ( $input['title'] ?? '' ) ) ) {
				return new WP_Error( 'missing_title', 'title is required for a custom-link menu item.' );
			}
			$data['menu-item-type'] = 'custom';
			$data['menu-item-url']  = $url;
			break;
	}
	return fcmcp_apply_menu_item_fields( $data, $input );
}

/** Resolve a menu reference (numeric id, slug, or name) to a menu term, or WP_Error. */
function fcmcp_resolve_menu( $ref ) {
	if ( is_numeric( $ref ) ) {
		$ref = (int) $ref;
	}
	$menu = $ref ? wp_get_nav_menu_object( $ref ) : false;
	if ( ! $menu ) {
		return new WP_Error( 'not_found', 'Menu not found.' );
	}
	return $menu;
}

/** The nav_menu term id a given menu item belongs to (0 if none). */
function fcmcp_menu_id_for_item( int $item_id ): int {
	$terms = wp_get_object_terms( $item_id, 'nav_menu' );
	return ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0]->term_id : 0;
}

function fcmcp_list_menus( $input = array() ) {
	$menus     = wp_get_nav_menus();
	$locations = get_nav_menu_locations();
	$by_menu   = array();
	foreach ( (array) $locations as $loc => $mid ) {
		$by_menu[ (int) $mid ][] = $loc;
	}
	$out = array();
	foreach ( (array) $menus as $m ) {
		$out[] = array(
			'id'        => (int) $m->term_id,
			'name'      => $m->name,
			'slug'      => $m->slug,
			'count'     => (int) $m->count,
			'locations' => $by_menu[ (int) $m->term_id ] ?? array(),
		);
	}
	$registered = function_exists( 'get_registered_nav_menus' ) ? get_registered_nav_menus() : array();
	return array( 'menus' => $out, 'registered_locations' => $registered );
}

function fcmcp_get_menu( $input ) {
	$menu = fcmcp_resolve_menu( $input['menu'] ?? null );
	if ( is_wp_error( $menu ) ) {
		return $menu;
	}
	$items = wp_get_nav_menu_items( $menu->term_id );
	$out   = array_map( 'fcmcp_menu_item_to_array', $items ? $items : array() );
	return array(
		'id'    => (int) $menu->term_id,
		'name'  => $menu->name,
		'slug'  => $menu->slug,
		'items' => $out,
	);
}

function fcmcp_create_menu( $input ) {
	$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
	if ( '' === $name ) {
		return new WP_Error( 'missing_name', 'name is required.' );
	}
	if ( wp_get_nav_menu_object( $name ) ) {
		return new WP_Error( 'menu_exists', 'A menu with that name already exists.' );
	}
	$id = wp_create_nav_menu( $name );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	return array( 'id' => (int) $id, 'name' => $name );
}

function fcmcp_add_menu_item( $input ) {
	$menu = fcmcp_resolve_menu( $input['menu'] ?? null );
	if ( is_wp_error( $menu ) ) {
		return $menu;
	}
	$data = fcmcp_build_menu_item_args( (array) $input );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$item_id = wp_update_nav_menu_item( $menu->term_id, 0, $data );
	if ( is_wp_error( $item_id ) ) {
		return $item_id;
	}
	return array(
		'menu_id' => (int) $menu->term_id,
		'item_id' => (int) $item_id,
		'title'   => $data['menu-item-title'] ?? '',
	);
}

function fcmcp_update_menu_item( $input ) {
	$id   = absint( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Menu item not found.' );
	}
	$menu_id  = fcmcp_menu_id_for_item( $id );
	$existing = wp_setup_nav_menu_item( $post );

	// Start from the item's current values so an unspecified field is preserved
	// (wp_update_nav_menu_item blanks anything missing from the data array).
	$data = array(
		'menu-item-type'      => $existing->type,
		'menu-item-object'    => $existing->object,
		'menu-item-object-id' => (int) $existing->object_id,
		'menu-item-url'       => $existing->url,
		'menu-item-title'     => $existing->title,
		'menu-item-parent-id' => (int) $existing->menu_item_parent,
		'menu-item-position'  => (int) $existing->menu_order,
		'menu-item-target'    => $existing->target,
		'menu-item-status'    => 'publish',
	);
	if ( 'custom' === $existing->type && array_key_exists( 'url', $input ) ) {
		$data['menu-item-url'] = esc_url_raw( (string) $input['url'] );
	}
	$data = fcmcp_apply_menu_item_fields( $data, $input );

	$r = wp_update_nav_menu_item( $menu_id, $id, $data );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return array( 'id' => $id, 'menu_id' => (int) $menu_id );
}

function fcmcp_remove_menu_item( $input ) {
	$id   = absint( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Menu item not found.' );
	}
	if ( ! wp_delete_post( $id, true ) ) {
		return new WP_Error( 'delete_failed', 'Could not delete the menu item.' );
	}
	return array( 'id' => $id, 'deleted' => true );
}

function fcmcp_reorder_menu( $input ) {
	$menu = fcmcp_resolve_menu( $input['menu'] ?? null );
	if ( is_wp_error( $menu ) ) {
		return $menu;
	}
	$order = $input['item_ids'] ?? array();
	if ( ! is_array( $order ) || ! $order ) {
		return new WP_Error( 'bad_order', 'item_ids must be a non-empty array of menu item ids.' );
	}
	// Setting menu_order directly reorders without disturbing the items' other
	// nav-menu meta (unlike re-running wp_update_nav_menu_item per item).
	$pos     = 0;
	$ordered = array();
	foreach ( $order as $iid ) {
		$iid  = absint( $iid );
		$post = get_post( $iid );
		if ( ! $post || 'nav_menu_item' !== $post->post_type || (int) fcmcp_menu_id_for_item( $iid ) !== (int) $menu->term_id ) {
			continue;
		}
		$pos++;
		wp_update_post( array( 'ID' => $iid, 'menu_order' => $pos ) );
		$ordered[] = $iid;
	}
	return array( 'menu_id' => (int) $menu->term_id, 'ordered' => $ordered );
}

/* ----------------------------------------------------------------------------
 * Intake REST endpoints — a thin, app-password-authenticated wrapper around the
 * create abilities, for non-MCP automation (e.g. the firstchurchnews email
 * worker). One POST per draft; all date/time/recurrence/CTA normalization is
 * handled by the same fcmcp_create_* functions the MCP tools use.
 *
 *   POST /wp-json/firstchurch/v1/intake/event         (body = create-event input)
 *   POST /wp-json/firstchurch/v1/intake/announcement  (body = create-announcement input)
 *
 * Auth: HTTP Basic with an Application Password for an mcp_editor-role user.
 * Returns 201 with { id, status, edit_url, event|announcement }, or 4xx
 * { error, code }. Everything is created as a draft unless status says otherwise.
 * ------------------------------------------------------------------------- */

function fcmcp_rest_json_params( WP_REST_Request $req ): array {
	$p = $req->get_json_params();
	return is_array( $p ) ? $p : array();
}

function fcmcp_rest_intake_response( $result ) {
	if ( is_wp_error( $result ) ) {
		$status = ( 'not_found' === $result->get_error_code() ) ? 404 : 400;
		return new WP_REST_Response(
			array( 'error' => $result->get_error_message(), 'code' => $result->get_error_code() ),
			$status
		);
	}
	return new WP_REST_Response( $result, 201 );
}

add_action(
	'rest_api_init',
	static function () {
		$can_write = static function () {
			return current_user_can( 'edit_posts' );
		};

		register_rest_route(
			'firstchurch/v1',
			'/intake/event',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can_write,
				'callback'            => static function ( WP_REST_Request $req ) {
					$input = fcmcp_rest_json_params( $req );
					if ( '' === trim( (string) ( $input['title'] ?? '' ) ) ) {
						return new WP_REST_Response( array( 'error' => 'title is required.', 'code' => 'missing_title' ), 400 );
					}
					return fcmcp_rest_intake_response( fcmcp_create_event( $input ) );
				},
			)
		);

		register_rest_route(
			'firstchurch/v1',
			'/intake/announcement',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can_write,
				'callback'            => static function ( WP_REST_Request $req ) {
					$input = fcmcp_rest_json_params( $req );
					if ( '' === trim( (string) ( $input['title'] ?? '' ) ) || '' === trim( (string) ( $input['content'] ?? '' ) ) ) {
						return new WP_REST_Response( array( 'error' => 'title and content are required.', 'code' => 'missing_fields' ), 400 );
					}
					return fcmcp_rest_intake_response( fcmcp_create_announcement( $input ) );
				},
			)
		);
	}
);
