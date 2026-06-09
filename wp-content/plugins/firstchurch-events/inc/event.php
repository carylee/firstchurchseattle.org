<?php
/**
 * The event data model: CTC-shaped recurrence meta in, derived RRULE + human
 * "when" out (reusing Recurrence::toRrule and the spine's EventWhen), plus the
 * one writer shared by the MCP path and the editor metabox.
 *
 * @package FirstChurch\Events
 */

use FirstChurch\Events\Recurrence;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The recurrence/when field array consumed by both Recurrence::toRrule() and
 * \FirstChurch\Happenings\EventWhen::format() (deliberately the same CTC shape).
 *
 * @return array<string,mixed>
 */
function fce_recurrence_fields( int $post_id ): array {
	$freq = (string) get_post_meta( $post_id, FCE_RECUR, true );
	return array(
		'start'           => (string) get_post_meta( $post_id, FCE_DTSTART, true ),
		// Recurrence::toRrule() reads `recurrence`; EventWhen::format() reads
		// `freq` — give both the same value so each consumer is satisfied.
		'recurrence'      => $freq,
		'freq'            => $freq,
		'weekly_interval' => (int) get_post_meta( $post_id, FCE_WK_INT, true ),
		'weekly_days'     => (string) get_post_meta( $post_id, FCE_WK_DAYS, true ),
		'monthly_type'    => (string) get_post_meta( $post_id, FCE_MO_TYPE, true ),
		'monthly_week'    => (string) get_post_meta( $post_id, FCE_MO_WEEK, true ),
		'end_date'        => (string) get_post_meta( $post_id, FCE_END, true ),
		'start_time'      => (string) get_post_meta( $post_id, FCE_TIME, true ),
		'venue'           => (string) get_post_meta( $post_id, FCE_VENUE, true ),
	);
}

/** Derived RRULE (never stored), or '' for a one-off. */
function fce_rrule( int $post_id ): string {
	return (string) ( Recurrence::toRrule( fce_recurrence_fields( $post_id ) ) ?? '' );
}

/** Human "when" — reuses the spine's church-phrased EventWhen when available. */
function fce_when( int $post_id ): string {
	$f = fce_recurrence_fields( $post_id );
	if ( class_exists( '\FirstChurch\Happenings\EventWhen' ) ) {
		return \FirstChurch\Happenings\EventWhen::format( $f );
	}
	$lead = $f['start'] ? date_i18n( 'F j', (int) strtotime( $f['start'] ) ) : '';
	return trim( $lead . ( '' !== $f['venue'] ? ' · ' . $f['venue'] : '' ) );
}

/** Cancelled occurrence dates (EXDATE), as a Y-m-d list. */
function fce_skip_dates( int $post_id ): array {
	return array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post_id, FCE_SKIP, true ) ) ) );
}

/**
 * First occurrence in [from, to] (inclusive) that isn't cancelled, or null.
 * One-offs (no rrule) use DTSTART. Cancellations filtered in PHP (robust).
 *
 * @param array<int,string> $skip Y-m-d dates to exclude.
 */
function fce_next_occurrence( string $dtstart, string $rrule, DateTimeInterface $from, DateTimeInterface $to, array $skip = array() ): ?DateTimeImmutable {
	if ( '' === $dtstart ) {
		return null;
	}
	if ( '' === $rrule ) {
		$d = new DateTimeImmutable( $dtstart );
		return ( $d >= $from && $d <= $to && ! in_array( $d->format( 'Y-m-d' ), $skip, true ) ) ? $d : null;
	}
	foreach ( new \FirstChurch\Events\Vendor\RRule\RRule( $rrule, new DateTime( $dtstart ) ) as $occ ) {
		$o = DateTimeImmutable::createFromInterface( $occ );
		if ( $o < $from ) {
			continue;
		}
		if ( $o > $to ) {
			return null;
		}
		if ( ! in_array( $o->format( 'Y-m-d' ), $skip, true ) ) {
			return $o;
		}
	}
	return null;
}

/**
 * Write event meta from a normalized authoring input — the single writer shared
 * by the MCP abilities and the editor metabox. The friendly recurrence object is
 * mapped to the stored CTC-shaped meta.
 *
 * $in keys (all optional except as enforced by callers): date, time, venue,
 * registration_url, skip_dates[], recurrence{ frequency, interval, weekdays[],
 * monthly_weeks[], until }.
 *
 * @param array<string,mixed> $in
 */
function fce_write_event( int $post_id, array $in ): void {
	if ( array_key_exists( 'date', $in ) ) {
		update_post_meta( $post_id, FCE_DTSTART, sanitize_text_field( (string) $in['date'] ) );
	}
	if ( array_key_exists( 'time', $in ) ) {
		update_post_meta( $post_id, FCE_TIME, sanitize_text_field( (string) $in['time'] ) );
	}
	if ( array_key_exists( 'venue', $in ) ) {
		update_post_meta( $post_id, FCE_VENUE, sanitize_text_field( (string) $in['venue'] ) );
	}
	if ( array_key_exists( 'registration_url', $in ) ) {
		update_post_meta( $post_id, FCE_REGURL, esc_url_raw( (string) $in['registration_url'] ) );
	}
	if ( array_key_exists( 'skip_dates', $in ) ) {
		$dates = array_filter( array_map( 'sanitize_text_field', (array) $in['skip_dates'] ) );
		update_post_meta( $post_id, FCE_SKIP, implode( ',', $dates ) );
	}
	if ( array_key_exists( 'recurrence', $in ) ) {
		$r    = (array) $in['recurrence'];
		$freq = strtolower( (string) ( $r['frequency'] ?? '' ) );
		$freq = in_array( $freq, array( 'weekly', 'monthly', 'yearly' ), true ) ? $freq : '';
		update_post_meta( $post_id, FCE_RECUR, $freq );
		update_post_meta( $post_id, FCE_WK_INT, max( 1, (int) ( $r['interval'] ?? 1 ) ) );

		$valid = array( 'SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA' );
		$days  = array_filter(
			array_map( static fn ( $d ) => strtoupper( trim( (string) $d ) ), (array) ( $r['weekdays'] ?? array() ) ),
			static fn ( $d ) => in_array( $d, $valid, true )
		);
		update_post_meta( $post_id, FCE_WK_DAYS, implode( ',', $days ) );

		$weeks = array_filter( array_map(
			static fn ( $w ) => strtolower( trim( (string) $w ) ) === 'last' ? 'last' : (string) (int) $w,
			(array) ( $r['monthly_weeks'] ?? array() )
		) );
		update_post_meta( $post_id, FCE_MO_TYPE, $weeks ? 'week' : 'day' );
		update_post_meta( $post_id, FCE_MO_WEEK, implode( ',', $weeks ) );

		update_post_meta( $post_id, FCE_END, sanitize_text_field( (string) ( $r['until'] ?? '' ) ) );
	}
}
