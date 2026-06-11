<?php
/**
 * First Church MCP Abilities — shared helpers.
 *
 * Date/time sanitizers, post/event/attachment shaping, managed-post gate, image + recurrence schemas and writers, and the shared status/trash helpers.
 *
 * Loaded by ../firstchurch-mcp-abilities.php (WordPress does not auto-load
 * mu-plugin subdirectories). Procedural, global namespace, no autoloader —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

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

/** Is this post in scope for the writer role? */
function fcmcp_is_managed_post( $post ): bool {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	// Managed types the mcp_editor role may edit/delete. Posts and pages are
	// included intentionally (full content management); enews_issue is managed so
	// the writer can draft/curate weekly e-news (firstchurch-enews). Attachments
	// carousel_card is managed so the writer can author evergreen standing cards
	// (firstchurch-carousel). Other CPTs (nav menus, blocks, etc.) remain out of
	// scope. (ctc_sermon was retired in favor of the YouTube service history —
	// see the theme's inc/redirects.php — so sermons are no longer managed.)
	return in_array( $post->post_type, array( 'ctc_event', 'post', 'page', 'enews_issue', 'carousel_card' ), true );
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
 * Shared status + trash helpers (used by every set-*-status / trash-* ability).
 * ------------------------------------------------------------------------- */

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

/**
 * Resolve human-supplied term names/slugs to term ids, creating any that don't
 * exist. Shared by the post/category + tag writers (and historically sermons).
 */
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
