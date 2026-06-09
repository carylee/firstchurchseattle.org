<?php
/**
 * The spine-shaped source readers: same Happening contract the carousel /
 * happenings plugin expect, so the spine merges these alongside CTC events
 * (see happenings_event_items / _occurrences). Recurrence/when derivation lives
 * in inc/event.php.
 *
 * @package FirstChurch\Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Project one fce_event post into the Happening shape. The single source of the
 * event→Happening mapping, shared by the list, occurrence, and by-id readers so
 * every surface (incl. the single page via happenings_item_by_id) sees the same
 * fields. `$id` and `$date` vary by caller (per-event vs per-occurrence).
 *
 * @return array<string,mixed>
 */
function fce_event_to_item( WP_Post $p, string $id, string $date ): array {
	$reg = (string) get_post_meta( $p->ID, FCE_REGURL, true );
	return array(
		'id'       => $id,
		'source'   => 'event',
		'layout'   => 'event',
		'title'    => html_entity_decode( get_the_title( $p ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		'date'     => $date,
		'when'     => fce_when( $p->ID ),
		// Machine-readable counterparts to the human `when`, for structured-data
		// surfaces (schema.org Event startDate/location). `when` stays the display
		// string; these are the parseable values behind it.
		'start'    => fce_start_iso( $p->ID, $date ),
		'location' => (string) get_post_meta( $p->ID, FCE_VENUE, true ),
		'ctaUrl'   => $reg ?: (string) get_permalink( $p ),
		'image'    => (string) get_the_post_thumbnail_url( $p, 'full' ),
		'url'      => (string) get_permalink( $p ),
	);
}

/**
 * Combine an occurrence date (Y-m-d) with the event's stored start time into an
 * ISO 8601 datetime in the site timezone — the machine `start` behind the human
 * `when`. Date-only (still valid ISO 8601 / schema startDate) when no clock-like
 * time is set; the clock guard mirrors EventWhen so free-text time_text values
 * ("After the service") never produce a bogus datetime.
 */
function fce_start_iso( int $post_id, string $date ): string {
	if ( '' === $date ) {
		return '';
	}
	$time = trim( (string) get_post_meta( $post_id, FCE_TIME, true ) );
	if ( ! preg_match( '/^\d{1,2}:\d{2}/', $time ) ) {
		return $date; // date-only
	}
	try {
		return ( new DateTimeImmutable( $date . ' ' . $time, wp_timezone() ) )->format( DateTimeInterface::ATOM );
	} catch ( \Exception $e ) {
		return $date;
	}
}

/**
 * Upcoming events as Happening items — one per event (its next non-cancelled
 * occurrence in the window). Each carries `date` so the spine can date-sort the
 * merged CTC + fce list.
 *
 * @return array<int,array<string,mixed>>
 */
function fce_event_items( int $weeks ): array {
	$from = new DateTimeImmutable( current_time( 'Y-m-d' ) );
	$to   = $from->modify( "+{$weeks} weeks" );

	$q = new WP_Query( array(
		'post_type'      => FCE_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'no_found_rows'  => true,
	) );

	$items = array();
	foreach ( $q->posts as $p ) {
		$dtstart = (string) get_post_meta( $p->ID, FCE_DTSTART, true );
		$next    = fce_next_occurrence( $dtstart, fce_rrule( $p->ID ), $from, $to, fce_skip_dates( $p->ID ) );
		if ( null === $next ) {
			continue;
		}
		$items[] = fce_event_to_item( $p, 'event-' . $p->ID, $next->format( 'Y-m-d' ) );
	}

	usort( $items, static fn ( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

	return $items;
}

/**
 * Every occurrence in [$from, $to] as Happening items — one per date, so a weekly
 * event yields one item per week. This is the calendar grid's source (vs.
 * fce_event_items(), which collapses each event to its next occurrence for a
 * list). The spine merges this with CTC for happenings_event_occurrences().
 *
 * @return array<int,array<string,mixed>>
 */
function fce_event_occurrences( DateTimeInterface $from, DateTimeInterface $to ): array {
	$q = new WP_Query( array(
		'post_type'      => FCE_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'no_found_rows'  => true,
	) );

	$items = array();
	foreach ( $q->posts as $p ) {
		$dtstart = (string) get_post_meta( $p->ID, FCE_DTSTART, true );
		$dates   = fce_occurrences_between( $dtstart, fce_rrule( $p->ID ), $from, $to, fce_skip_dates( $p->ID ) );
		foreach ( $dates as $d ) {
			// Per-occurrence id so the same event on two dates stays distinct.
			$items[] = fce_event_to_item( $p, 'event-' . $p->ID . '-' . $d->format( 'Ymd' ), $d->format( 'Y-m-d' ) );
		}
	}

	usort( $items, static fn ( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

	return $items;
}

/**
 * Project a SINGLE fce_event by post id into the Happening shape — the by-id
 * reader the spine's happenings_item_by_id() dispatches to (so the event single
 * page and carousel deck pins resolve fce events the same way every feed does).
 * `date` is the next non-cancelled occurrence within a year, or '' for an event
 * with none left (a past one-off still resolves so its page renders).
 *
 * @return array<string,mixed>|null Null if the post is missing/not a published fce_event.
 */
function fce_event_item( int $post_id ): ?array {
	$p = get_post( $post_id );
	if ( ! $p || FCE_CPT !== $p->post_type || 'publish' !== $p->post_status ) {
		return null;
	}
	$from = new DateTimeImmutable( current_time( 'Y-m-d' ) );
	$next = fce_next_occurrence(
		(string) get_post_meta( $p->ID, FCE_DTSTART, true ),
		fce_rrule( $p->ID ),
		$from,
		$from->modify( '+1 year' ),
		fce_skip_dates( $p->ID )
	);
	$item = fce_event_to_item( $p, 'event-' . $p->ID, $next ? $next->format( 'Y-m-d' ) : '' );

	// The by-id (detail) projection additionally carries the event's full raw body
	// (`content`), which the single page renders. Feed builders (fce_event_to_item)
	// deliberately omit it so /engage, the calendar, and the carousel stay lean and
	// don't carry/serialize full content per item. Surfaces render `content` (it's
	// raw post body, like get_the_content()); the lean `blurb` summary is separate.
	$content = trim( (string) $p->post_content );
	if ( '' !== $content ) {
		$item['content'] = $content;
	}

	return $item;
}
