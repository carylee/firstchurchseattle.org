<?php
/**
 * First Church MCP Abilities — events.
 *
 * Read + draft-first write abilities for ctc_event, plus search/query helpers.
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
		$can_edit      = static function () { return current_user_can( 'edit_posts' ); };

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
	}
);

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

