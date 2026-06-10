<?php
/**
 * MCP authoring path: create/update a lean event (incl. recurrence + cancelled
 * occurrences) from a friendly structured object — the agent-first authoring
 * surface. The recurrence object is mapped to the stored CTC-shaped meta by the
 * shared fce_write_event(); RRULE + "when" are derived (inc/event.php).
 *
 * @package FirstChurch\Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_abilities_api_init', static function () {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	$recurrence = array(
		'type'        => 'object',
		'description' => 'Optional. Omit for a one-off event.',
		'properties'  => array(
			'frequency'     => array( 'type' => 'string', 'enum' => array( 'weekly', 'monthly', 'yearly' ) ),
			'interval'      => array( 'type' => 'integer', 'description' => 'e.g. 2 = every other.' ),
			'weekdays'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Weekly: day codes ["SU","TH"].' ),
			'monthly_weeks' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Monthly nth-weekday: ["2","4"] or ["last"] (weekday taken from the date). Omit for monthly on the date\'s day-of-month.' ),
			'until'         => array( 'type' => 'string', 'description' => 'YYYY-MM-DD; recurrence stops after.' ),
		),
	);
	$fields = array(
		'title'            => array( 'type' => 'string' ),
		'date'             => array( 'type' => 'string', 'description' => 'First occurrence / anchor, YYYY-MM-DD. For monthly nth-weekday, its weekday is the recurring weekday.' ),
		'time'             => array( 'type' => 'string', 'description' => 'HH:MM (24h); omit for all-day.' ),
		'venue'            => array( 'type' => 'string' ),
		'registration_url' => array( 'type' => 'string' ),
		'recurrence'       => $recurrence,
		'kind'             => array( 'type' => 'string', 'enum' => array( '', 'rhythm', 'group', 'event' ), 'description' => 'Override the derived classification (rhythm = standing weekly pattern, group = ongoing gathering, event = time-bound). Usually omit: it is derived from recurrence. Empty string clears an override.' ),
		'skip_dates'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'YYYY-MM-DD occurrences to cancel (EXDATE).' ),
		'status'           => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ), 'default' => 'publish' ),
	);

	wp_register_ability( 'firstchurch/create-event-lean', array(
		'label'               => 'Create event (lean)',
		'description'         => 'Create an event in the lean RRULE backend. Recurrence is a friendly object; no raw RRULE and no roll-forward cron.',
		'category'            => 'firstchurch',
		'input_schema'        => array( 'type' => 'object', 'properties' => $fields, 'required' => array( 'title', 'date' ), 'additionalProperties' => false ),
		'execute_callback'    => 'fce_mcp_create_event',
		'permission_callback' => static fn () => current_user_can( 'edit_posts' ),
		'meta'                => array( 'mcp' => array( 'public' => true ), 'annotations' => array( 'readonly' => false, 'destructive' => false ) ),
	) );

	wp_register_ability( 'firstchurch/update-event-lean', array(
		'label'               => 'Update event (lean)',
		'description'         => 'Update a lean event by id (partial). Pass recurrence to change the pattern, skip_dates to cancel/restore occurrences.',
		'category'            => 'firstchurch',
		'input_schema'        => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ) ) + $fields, 'required' => array( 'id' ), 'additionalProperties' => false ),
		'execute_callback'    => 'fce_mcp_update_event',
		'permission_callback' => static fn ( $i = array() ) => isset( $i['id'] ) && current_user_can( 'edit_post', (int) $i['id'] ),
		'meta'                => array( 'mcp' => array( 'public' => true ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
	) );
} );

function fce_mcp_create_event( $in ) {
	if ( empty( $in['title'] ) || empty( $in['date'] ) ) {
		return new WP_Error( 'bad_input', 'title and date are required.' );
	}
	$status = ( ( $in['status'] ?? 'publish' ) === 'draft' ) ? 'draft' : 'publish';
	$id     = wp_insert_post( array( 'post_type' => FCE_CPT, 'post_status' => $status, 'post_title' => sanitize_text_field( (string) $in['title'] ) ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	fce_write_event( (int) $id, $in );
	return fce_mcp_result( (int) $id );
}

function fce_mcp_update_event( $in ) {
	$id   = (int) ( $in['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || FCE_CPT !== $post->post_type ) {
		return new WP_Error( 'not_found', 'event not found.' );
	}
	if ( array_key_exists( 'title', $in ) ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( (string) $in['title'] ) ) );
	}
	fce_write_event( $id, $in );
	return fce_mcp_result( $id );
}

/** Echo back the derived RRULE, next occurrence, and the human "when". */
function fce_mcp_result( int $id ): array {
	$rrule = fce_rrule( $id );
	$next  = fce_next_occurrence(
		(string) get_post_meta( $id, FCE_DTSTART, true ),
		$rrule,
		new DateTimeImmutable( current_time( 'Y-m-d' ) ),
		new DateTimeImmutable( '2100-01-01' ),
		fce_skip_dates( $id )
	);
	return array(
		'id'              => $id,
		'status'          => get_post_status( $id ),
		'rrule'           => $rrule ?: '(one-off)',
		'kind'            => fce_kind( $id ),
		'when'            => fce_when( $id ),
		'next_occurrence' => $next ? $next->format( 'Y-m-d' ) : null,
		'cancelled'       => fce_skip_dates( $id ),
	);
}
